<?php

namespace Tests\Unit;

use App\Core\Models\User;
use App\Core\Models\CommunityMessageThread;
use App\Core\Services\WebSocketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class WebSocketServiceTest extends TestCase
{
    use RefreshDatabase;

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
}
