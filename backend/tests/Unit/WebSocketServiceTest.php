<?php

namespace Tests\Unit;

use App\Core\Models\User;
use App\Core\Models\CommunityMessageThread;
use App\Core\Services\WebSocketService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class WebSocketServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected WebSocketService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WebSocketService::class);
    }

    public function test_room_global_authorization()
    {
        $user = User::factory()->create();
        $this->assertTrue($this->service->authorizeChannel($user, 'room.global'));
    }

    public function test_room_premium_lounge_authorization()
    {
        $premiumUser = User::factory()->create(['tier' => 'premium']);
        $standardUser = User::factory()->create(['tier' => 'standard']);
        $adminUser = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $adminUser->assignRole('admin');

        $this->assertTrue($this->service->authorizeChannel($premiumUser, 'room.premium-lounge'));
        $this->assertTrue($this->service->authorizeChannel($adminUser, 'room.premium-lounge'));
        $this->assertFalse($this->service->authorizeChannel($standardUser, 'room.premium-lounge'));
    }

    public function test_room_vendors_authorization()
    {
        $vendorUser = User::factory()->create(['is_approved_vendor' => true]);
        $standardUser = User::factory()->create(['is_approved_vendor' => false]);
        $adminUser = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $adminUser->assignRole('admin');

        $this->assertTrue($this->service->authorizeChannel($vendorUser, 'room.vendors'));
        $this->assertTrue($this->service->authorizeChannel($adminUser, 'room.vendors'));
        $this->assertFalse($this->service->authorizeChannel($standardUser, 'room.vendors'));
    }

    public function test_room_invalid_authorization()
    {
        $user = User::factory()->create();
        $this->assertFalse($this->service->authorizeChannel($user, 'room.unknown-room'));
    }

    public function test_message_fallback_queuing_stores_and_retrieves_messages_with_redis()
    {
        $channel = 'test_channel_' . uniqid();
        Redis::del("ws_messages:{$channel}");

        \Illuminate\Support\Carbon::setTestNow(now());
        $this->service->broadcast($channel, 'chat.message', ['text' => 'Message 1']);

        \Illuminate\Support\Carbon::setTestNow(now()->addSeconds(5));
        $this->service->broadcast($channel, 'chat.message', ['text' => 'Message 2']);

        $messages = $this->service->getMessages($channel);

        $this->assertCount(2, $messages);
        $this->assertEquals('Message 1', $messages[0]['data']['text']);
        $this->assertEquals('Message 2', $messages[1]['data']['text']);

        // Test since filter
        $sinceTimestamp = $messages[0]['timestamp'];
        $filtered = $this->service->getMessages($channel, $sinceTimestamp);
        $this->assertCount(1, $filtered);
        $this->assertEquals('Message 2', $filtered[0]['data']['text']);

        \Illuminate\Support\Carbon::setTestNow();
        Redis::del("ws_messages:{$channel}");
    }

    public function test_message_fallback_queuing_caps_at_100_messages()
    {
        $channel = 'test_capped_' . uniqid();
        Redis::del("ws_messages:{$channel}");

        for ($i = 1; $i <= 105; $i++) {
            $this->service->broadcast($channel, 'chat.message', ['index' => $i]);
        }

        $messages = $this->service->getMessages($channel);

        $this->assertCount(100, $messages);
        $this->assertEquals(6, $messages[0]['data']['index']);
        $this->assertEquals(105, $messages[99]['data']['index']);

        Redis::del("ws_messages:{$channel}");
    }

    public function test_presence_tracking_join_leave_and_members_with_redis()
    {
        $channel = 'room.test_' . uniqid();
        Redis::del("presence:{$channel}");

        $user1 = User::factory()->create(['username' => 'user_one']);
        $user2 = User::factory()->create(['username' => 'user_two']);

        $this->service->joinPresence($channel, $user1);
        $members = $this->service->getPresenceMembers($channel);

        $this->assertCount(1, $members);
        $this->assertArrayHasKey((string) $user1->id, $members);
        $this->assertEquals('user_one', $members[$user1->id]['username']);

        $this->service->joinPresence($channel, $user2);
        $members = $this->service->getPresenceMembers($channel);

        $this->assertCount(2, $members);
        $this->assertArrayHasKey((string) $user2->id, $members);

        $this->service->leavePresence($channel, $user1);
        $members = $this->service->getPresenceMembers($channel);

        $this->assertCount(1, $members);
        $this->assertArrayNotHasKey((string) $user1->id, $members);
        $this->assertArrayHasKey((string) $user2->id, $members);

        Redis::del("presence:{$channel}");
    }
}
