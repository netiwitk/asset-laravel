<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_the_admin_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_demo_login_works_only_in_demo_mode(): void
    {
        User::factory()->create(['email' => 'officer@demo.test']);

        config(['app.demo' => false]);
        $this->get('/demo/officer')->assertNotFound();
        $this->assertGuest();

        config(['app.demo' => true]);
        $this->get('/demo/officer')->assertRedirect('/admin');
        $this->assertAuthenticated();
    }
}
