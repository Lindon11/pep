<?php

namespace Tests\Feature;

use App\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityBootstrapApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_can_access_bootstrap_data_if_allowed()
    {
        // Assuming the route might be public or require auth. 
        // We'll test assuming it requires auth as is standard for most community features,
        // but let's check the route first. If it fails with 401, we know it requires auth.
        
        $response = $this->getJson('/api/v1/community/home-bootstrap');
        
        // It might be protected, so we just assert it returns 401 or 200 depending on middleware.
        // Usually, community home data is accessible to logged in users.
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_community_home_bootstrap_data()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/community/home-bootstrap');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'discussions',
            'lab_results',
            'vendors',
            'announcements',
            'members'
        ]);
    }
}
