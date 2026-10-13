<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise CSRF checks, which Laravel normally skips in tests.
        $this->app['env'] = 'local';
    }

    public function test_fresh_session_token_allows_login_after_the_page_token_expires(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.test',
            'password' => bcrypt('test-password'),
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->withSession(['_token' => 'replacement-session-token']);
        $response = $this->get('/login/session-token');
        $response->assertOk()->assertJson(['token' => 'replacement-session-token']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $this->post('/login', [
            '_token' => $response->json('token'),
            'email' => $user->email,
            'password' => 'test-password',
        ])->assertRedirect('dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_login_returns_to_form_without_flashing_password(): void
    {
        $this->withSession(['_token' => 'current-token'])->post('/login', [
            '_token' => 'expired-token',
            'email' => 'login@example.test',
            'password' => 'secret-password',
            'remember' => 'on',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('error')
            ->assertSessionHas('_old_input.email', 'login@example.test')
            ->assertSessionMissing('_old_input.password');
        $this->assertGuest();
    }

    public function test_other_forms_still_reject_invalid_csrf_tokens(): void
    {
        $this->withSession(['_token' => 'current-token'])
            ->post('/logout', ['_token' => 'expired-token'])->assertStatus(419);
    }

    public function test_login_page_is_not_cached(): void
    {
        $response = $this->get('/login');
        $response->assertOk()->assertSee('session-token', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }
}
