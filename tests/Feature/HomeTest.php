<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login_from_home()
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_home_page_renders_for_a_signed_in_user()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('home'));
    }
}
