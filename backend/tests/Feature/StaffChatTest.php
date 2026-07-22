<?php

namespace Tests\Feature;

use App\Core\Middleware\VerifyLicense;
use App\Core\Models\StaffChatMessage;
use App\Core\Models\StaffChatReadStatus;
use App\Core\Models\User;
use App\Core\Services\WebSocketService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class StaffChatTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyLicense::class);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create([
            'username' => 'AdminUser',
        ]);
        $admin->assignRole('admin');
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_send_message_creates_message_and_broadcasts_via_websocket_service(): void
    {
        $admin = $this->actingAsAdmin();

        $mockWs = Mockery::mock(WebSocketService::class)->makePartial();
        $mockWs->shouldReceive('broadcast')
            ->once()
            ->with('staff-chat', 'chat.message', Mockery::on(function ($data) use ($admin) {
                return isset($data['message']) &&
                    $data['message']['user_id'] === $admin->id &&
                    $data['message']['content'] === 'Hello team!';
            }));
        $this->app->instance(WebSocketService::class, $mockWs);

        $response = $this->postJson('/api/v1/admin/staff-chat/messages', [
            'content' => 'Hello team!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message.content', 'Hello team!')
            ->assertJsonPath('message.user_id', $admin->id);

        $this->assertDatabaseHas('staff_chat_messages', [
            'user_id' => $admin->id,
            'content' => 'Hello team!',
        ]);
    }

    public function test_messages_index_returns_messages_cleanly_without_schema_checks(): void
    {
        $admin = $this->actingAsAdmin();

        $msg1 = StaffChatMessage::create([
            'user_id' => $admin->id,
            'content' => 'Test message 1',
        ]);
        $msg1->created_at = now()->subMinute();
        $msg1->save();

        $msg2 = StaffChatMessage::create([
            'user_id' => $admin->id,
            'content' => 'Test message 2',
        ]);
        $msg2->created_at = now();
        $msg2->save();

        $response = $this->getJson('/api/v1/admin/staff-chat/messages');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'messages',
                'next_cursor',
                'prev_cursor',
                'per_page',
                'online_staff',
                'current_user_id',
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.content', 'Test message 2')
            ->assertJsonPath('data.1.content', 'Test message 1');
    }

    public function test_messages_index_supports_cursor_pagination(): void
    {
        $admin = $this->actingAsAdmin();

        $msg1 = StaffChatMessage::create([
            'user_id' => $admin->id,
            'content' => 'Old message 1',
        ]);
        $msg1->created_at = now()->subMinutes(10);
        $msg1->save();

        $msg2 = StaffChatMessage::create([
            'user_id' => $admin->id,
            'content' => 'Middle message 2',
        ]);
        $msg2->created_at = now()->subMinutes(5);
        $msg2->save();

        $msg3 = StaffChatMessage::create([
            'user_id' => $admin->id,
            'content' => 'Newest message 3',
        ]);
        $msg3->created_at = now();
        $msg3->save();

        // Fetch initial page with per_page = 2
        $response = $this->getJson('/api/v1/admin/staff-chat/messages?per_page=2');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'messages',
                'next_cursor',
                'prev_cursor',
                'per_page',
                'online_staff',
                'current_user_id',
            ])
            ->assertJsonCount(2, 'data');

        $nextCursor = $response->json('next_cursor');
        $this->assertNotNull($nextCursor);

        // Fetch next (older) page passing cursor parameter
        $responseNext = $this->getJson('/api/v1/admin/staff-chat/messages?per_page=2&cursor=' . urlencode($nextCursor));

        $responseNext->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.content', 'Old message 1');

        $prevCursor = $responseNext->json('prev_cursor');
        $this->assertNotNull($prevCursor);
    }

    public function test_unread_endpoint_returns_count_cleanly_without_schema_checks(): void
    {
        $admin = $this->actingAsAdmin();
        $otherUser = User::factory()->create(['username' => 'OtherStaff']);

        $msg = StaffChatMessage::create([
            'user_id' => $otherUser->id,
            'content' => 'Urgent update',
        ]);

        $response = $this->getJson('/api/v1/admin/staff-chat/unread');

        $response->assertStatus(200)
            ->assertJsonPath('count', 1);

        StaffChatReadStatus::create([
            'user_id' => $admin->id,
            'last_read_message_id' => $msg->id,
            'last_read_at' => now(),
        ]);

        $responseAfterRead = $this->getJson('/api/v1/admin/staff-chat/unread');
        $responseAfterRead->assertStatus(200)
            ->assertJsonPath('count', 0);
    }

    public function test_unauthorized_user_cannot_access_staff_chat(): void
    {
        $regularUser = User::factory()->create();
        Sanctum::actingAs($regularUser);

        $response = $this->getJson('/api/v1/admin/staff-chat/messages');
        $response->assertStatus(403);
    }
}
