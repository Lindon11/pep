<?php

namespace App\Plugins\PeptideVendorsBot\Services;

use App\Plugins\PeptideVendorsBot\Models\Review;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReviewService
{
    const REVIEWS_CHAT_ID = '-1004376480189';
    const REVIEWS_THREAD_ID = 120;

    const STATUS_NEW = 'new';
    const STATUS_REVIEWED = 'reviewed';
    const STATUS_VERIFIED = 'verified';
    const STATUS_TRUSTED = 'trusted';

    public function isReviewTopic(array $message): bool
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $threadId = isset($message['is_topic_message']) ? (int) ($message['message_thread_id'] ?? 0) : 0;

        return $chatId === self::REVIEWS_CHAT_ID && $threadId === self::REVIEWS_THREAD_ID;
    }

    public function parseReview(array $message): ?array
    {
        $text = trim((string) ($message['text'] ?? $message['caption'] ?? ''));

        if ($text === '') {
            return null;
        }

        $lines = explode("\n", $text);
        $vendor = null;
        $rating = null;
        $feedback = '';
        $reviewerName = null;
        $inComments = false;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^Vendor:\s*(.+)$/i', $line, $m)) {
                $vendor = trim($m[1]);
            } elseif (preg_match('/^Name(?:\:\s*|\s+)(.+)$/i', $line, $m)) {
                $reviewerName = trim($m[1]);
            } elseif (preg_match('/^Rating:\s*(.+)$/i', $line, $m)) {
                $rating = $this->parseRating(trim($m[1]));
            } elseif (preg_match('/^Comments:\s*(.*)$/i', $line, $m)) {
                $feedback = trim($m[1]);
                $inComments = true;
            } elseif ($inComments) {
                $feedback .= "\n" . $line;
            }
        }

        if ($vendor === null || $rating === null) {
            return null;
        }

        $rating = max(1, min(5, $rating));
        $feedback = trim($feedback);

        return [
            'vendor' => $vendor,
            'rating' => $rating,
            'feedback' => $feedback,
            'reviewer_name' => $reviewerName,
        ];
    }

    private function parseRating(string $value): ?int
    {
        if (preg_match('/^(\d+(?:\.\d+)?)\s*\/?\s*\d*$/', $value, $m)) {
            return (int) round((float) $m[1]);
        }

        $starCount = mb_substr_count($value, '⭐');
        if ($starCount > 0) {
            return min(5, $starCount);
        }

        return null;
    }

    public function storeReview(array $parsed, array $message): Review
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $userId = (string) ($message['from']['id'] ?? '');
        $userName = (string) ($message['from']['first_name'] ?? '');
        $username = (string) ($message['from']['username'] ?? '');
        $messageId = (int) ($message['message_id'] ?? 0);

        $displayName = $userName !== '' ? $userName : ($username !== '' ? "@{$username}" : 'Unknown');

        $reviewerName = $parsed['reviewer_name'] ?? null;

        if ($reviewerName && strtolower($reviewerName) !== strtolower($displayName)) {
            $existing = Review::where('vendor', $parsed['vendor'])
                ->where('reviewer_name', $reviewerName)
                ->first();
        } else {
            $existing = Review::where('vendor', $parsed['vendor'])
                ->where('user_id', $userId)
                ->first();
        }

        if ($existing) {
            $existing->update([
                'rating' => $parsed['rating'],
                'feedback' => $parsed['feedback'],
                'user_name' => $displayName,
                'reviewer_name' => $reviewerName,
                'telegram_message_id' => $messageId,
            ]);

            Log::info('peptidebot.review.updated', [
                'vendor' => $parsed['vendor'],
                'user_id' => $userId,
                'rating' => $parsed['rating'],
            ]);

            return $existing;
        }

        $review = Review::create([
            'vendor' => $parsed['vendor'],
            'rating' => $parsed['rating'],
            'feedback' => $parsed['feedback'],
            'user_id' => $userId,
            'user_name' => $displayName,
            'reviewer_name' => $parsed['reviewer_name'] ?? null,
            'chat_id' => $chatId,
            'telegram_message_id' => $messageId,
        ]);

        Log::info('peptidebot.review.created', [
            'vendor' => $parsed['vendor'],
            'user_id' => $userId,
            'rating' => $parsed['rating'],
        ]);

        return $review;
    }

    public function vendorStats(?string $vendor = null): array
    {
        $query = Review::query();

        if ($vendor !== null) {
            $query->where('vendor', $vendor);
        }

        $reviews = $query->get();

        if ($reviews->isEmpty()) {
            return [];
        }

        $grouped = $reviews->groupBy('vendor');

        return $grouped->map(function ($vendorReviews, $vendorName) {
            $total = $vendorReviews->count();
            $avgRating = $vendorReviews->avg('rating');
            $positive = $vendorReviews->where('rating', '>=', 4)->count();
            $negative = $vendorReviews->where('rating', '<=', 2)->count();

            return [
                'vendor' => $vendorName,
                'total_reviews' => $total,
                'average_rating' => round($avgRating, 1),
                'positive_reviews' => $positive,
                'negative_reviews' => $negative,
                'status' => $this->statusLevel($total, $avgRating),
                'status_label' => $this->statusLabel($total, $avgRating),
            ];
        })->values()->toArray();
    }

    public function statusLevel(int $total, float $avgRating): string
    {
        if ($total >= 25 && $avgRating > 4.5) return self::STATUS_TRUSTED;
        if ($total >= 10 && $avgRating > 4.0) return self::STATUS_VERIFIED;
        if ($total >= 3) return self::STATUS_REVIEWED;
        return self::STATUS_NEW;
    }

    public function statusLabel(int $total, float $avgRating): string
    {
        return match ($this->statusLevel($total, $avgRating)) {
            self::STATUS_TRUSTED => "⭐ Trusted Vendor",
            self::STATUS_VERIFIED => "🟢 Community Verified",
            self::STATUS_REVIEWED => "🟡 Reviewed Vendor",
            default => "⚪ New Vendor",
        };
    }

    public function formatVendorStats(array $stats): string
    {
        $lines = [];

        foreach ($stats as $vendor) {
            $stars = str_repeat('⭐', (int) round($vendor['average_rating']));
            $lines[] = "{$vendor['vendor']}";
            $lines[] = "{$stars} {$vendor['average_rating']}/5 ({$vendor['total_reviews']} reviews)";
            $lines[] = "{$vendor['status_label']}";
            $lines[] = "";
        }

        return implode("\n", $lines);
    }
}
