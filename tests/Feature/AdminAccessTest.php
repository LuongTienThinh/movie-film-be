<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_login_page_is_available_to_guests(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertViewIs('admin.login');
    }
}
