<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_a_signed_in_user_reaches_the_dashboard(): void
    {
        $user = User::create([
            'name'     => 'Examplezzz',
            'username' => 'examplezzz',
            'email'    => 'examplezzz@example.test',
            'password' => bcrypt('secret'),
            'is_admin' => true,
        ]);

        $this->actingAs($user)->get('/')->assertOk();
    }
}
