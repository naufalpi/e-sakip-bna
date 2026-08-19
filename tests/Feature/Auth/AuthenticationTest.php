<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_login_captcha_can_be_refreshed()
    {
        $response = $this->getJson('/login/captcha');

        $response
            ->assertOk()
            ->assertJsonStructure(['captchaQuestion']);

        $this->assertIsArray(session(LoginRequest::CAPTCHA_SESSION_KEY));
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this
            ->withSession($this->loginSecuritySession())
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
                'captcha_answer' => '11',
            ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this
            ->withSession($this->loginSecuritySession())
            ->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
                'captcha_answer' => '11',
            ]);

        $this->assertGuest();
    }

    public function test_json_login_returns_the_dashboard_redirect()
    {
        $user = User::factory()->create();

        $response = $this
            ->withSession($this->loginSecuritySession())
            ->postJson('/login', [
                'email' => $user->email,
                'password' => 'password',
                'captcha_answer' => '11',
            ]);

        $this->assertAuthenticated();
        $response
            ->assertOk()
            ->assertJsonPath('redirect', route('dashboard'));
    }

    public function test_login_rejects_bot_honeypot_submissions()
    {
        $user = User::factory()->create();

        $response = $this
            ->withSession($this->loginSecuritySession())
            ->from('/login')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
                'login_website' => 'https://spam.test',
                'captcha_answer' => '11',
            ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_login_rejects_an_invalid_captcha_answer()
    {
        $user = User::factory()->create();

        $response = $this
            ->withSession($this->loginSecuritySession())
            ->from('/login')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
                'captcha_answer' => '10',
            ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('captcha_answer');
    }

    public function test_rate_limited_login_returns_a_user_friendly_message()
    {
        $user = User::factory()->create();
        $throttleKey = strtolower($user->email).'|127.0.0.1';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            RateLimiter::hit($throttleKey, 300);
        }

        try {
            $response = $this
                ->withSession($this->loginSecuritySession())
                ->postJson('/login', [
                    'email' => $user->email,
                    'password' => 'password',
                    'captcha_answer' => '11',
                ]);

            $response
                ->assertUnprocessable()
                ->assertJsonPath('errors.email.0', fn ($message) => is_string($message)
                    && str_starts_with($message, 'Terlalu banyak percobaan login.')
                    && ! str_contains($message, 'auth.throttle'));
        } finally {
            RateLimiter::clear($throttleKey);
        }
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    /**
     * @return array<string, mixed>
     */
    private function loginSecuritySession(): array
    {
        return [
            LoginRequest::FORM_ISSUED_AT_SESSION_KEY => now()->subSeconds(2)->timestamp,
            LoginRequest::CAPTCHA_SESSION_KEY => [
                'answer' => '11',
                'issued_at' => now()->timestamp,
            ],
        ];
    }
}
