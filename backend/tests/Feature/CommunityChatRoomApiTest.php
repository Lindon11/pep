<?php

namespace Tests\Feature;

use App\Core\Models\CommunityRoomMessage;
use App\Core\Models\User;
use App\Core\Services\WebSocketService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class CommunityChatRoomApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_fetch_messages_from_room_if_authorized()
    {
        $user = User::factory()->create();

        // Mock WebSocketService authorizeChannel to return true
        $mock = Mockery::mock(WebSocketService::class)->makePartial();
        $mock->shouldReceive('authorizeChannel')->andReturn(true);
        $this->app->instance(WebSocketService::class, $mock);

        CommunityRoomMessage::create([
            'room' => 'general',
            'sender_user_id' => $user->id,
            'body' => 'Hello World',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/community/chat/rooms/general');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.body', 'Hello World');
    }

    public function test_user_cannot_fetch_messages_if_not_authorized()
    {
        $user = User::factory()->create();

        $mock = Mockery::mock(WebSocketService::class)->makePartial();
        $mock->shouldReceive('authorizeChannel')->andReturn(false);
        $this->app->instance(WebSocketService::class, $mock);

        $response = $this->actingAs($user)->getJson('/api/v1/community/chat/rooms/general');

        $response->assertStatus(403);
    }

    public function test_user_can_post_message_if_authorized()
    {
        $user = User::factory()->create();

        $mock = Mockery::mock(WebSocketService::class)->makePartial();
        $mock->shouldReceive('authorizeChannel')->andReturn(true);
        $mock->shouldReceive('broadcast')->once();
        $this->app->instance(WebSocketService::class, $mock);

        $response = $this->actingAs($user)->postJson('/api/v1/community/chat/rooms/general/messages', [
            'body' => 'Testing message'
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.body', 'Testing message');
        
        $this->assertDatabaseHas('community_room_messages', [
            'room' => 'general',
            'body' => 'Testing message',
            'sender_user_id' => $user->id,
        ]);
    }

    public function test_user_can_fetch_messages_with_cursor_pagination()
    {
        $user = User::factory()->create();

        $mock = Mockery::mock(WebSocketService::class)->makePartial();
        $mock->shouldReceive('authorizeChannel')->andReturn(true);
        $this->app->instance(WebSocketService::class, $mock);

        CommunityRoomMessage::create([
            'room' => 'general',
            'sender_user_id' => $user->id,
            'body' => 'Message 1',
            'sent_at' => now()->subMinutes(10),
        ]);

        CommunityRoomMessage::create([
            'room' => 'general',
            'sender_user_id' => $user->id,
            'body' => 'Message 2',
            'sent_at' => now()->subMinutes(5),
        ]);

        CommunityRoomMessage::create([
            'room' => 'general',
            'sender_user_id' => $user->id,
            'body' => 'Message 3',
            'sent_at' => now(),
        ]);

        // Request initial page with per_page = 2
        $response = $this->actingAs($user)->getJson('/api/v1/community/chat/rooms/general?per_page=2');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        $nextCursor = $response->json('next_cursor') ?? $response->json('meta.next_cursor');
        $this->assertNotNull($nextCursor);

        // Fetch older page passing cursor parameter
        $responseNext = $this->actingAs($user)->getJson('/api/v1/community/chat/rooms/general?per_page=2&cursor=' . urlencode($nextCursor));

        $responseNext->assertStatus(200);
        $responseNext->assertJsonCount(1, 'data');
        $responseNext->assertJsonPath('data.0.body', 'Message 1');
    }
}
