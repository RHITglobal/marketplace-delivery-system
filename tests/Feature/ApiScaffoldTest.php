<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiScaffoldTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_via_api(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'customer',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure(['message', 'token', 'user' => ['id', 'email', 'role']]);
    }

    public function test_catalog_products_endpoint_is_publicly_accessible(): void
    {
        $response = $this->getJson('/api/v1/catalog/products');

        $response->assertOk();
    }
}
