<?php

namespace Tests\Feature;

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User;
use Mockery;
use Tests\TestCase;
use Laravel\Socialite\Two\InvalidStateException;

class SocialiteAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_socialite_routes_reject_unknown_providers_before_resolving_a_driver(): void
    {
        Socialite::shouldReceive('driver')->never();

        $this->get('/auth/not-configured/redirect')->assertNotFound();
        $this->get('/auth/not-configured/callback')->assertNotFound();
    }

    public function test_socialite_signup_follows_registration_flag(): void
    {
        config(['spectacular.registration' => false]);
        $this->mockSocialiteUser('github', 'social-123', 'New Account', 'new@example.test');

        $this->get('/auth/github/callback')
            ->assertNotFound();

        $this->assertGuest('web');
        $this->assertDatabaseMissing('accounts', [
            'email' => 'new@example.test',
        ]);
    }

    public function test_socialite_login_allows_existing_social_accounts_when_registration_is_disabled(): void
    {
        config(['spectacular.registration' => false]);

        $account = Account::factory()->create([
            'socialite_provider' => 'github',
            'socialite_provider_id' => 'social-123',
        ]);

        $this->mockSocialiteUser('github', 'social-123', 'Existing Account', $account->email);

        $this->get('/auth/github/callback')
            ->assertRedirect('/app');

        $this->assertAuthenticatedAs($account, 'web');
    }

    public function test_socialite_callback_reports_when_email_belongs_to_an_unlinked_account(): void
    {
        Account::factory()->create([
            'email' => 'existing@example.test',
        ]);

        $this->mockSocialiteUser('github', 'social-123', 'Existing Account', 'existing@example.test');

        $this->get('/auth/github/callback')
            ->assertStatus(409)
            ->assertSee('already exists')
            ->assertSee('not connected to this social login provider');
    }

    public function test_socialite_callback_shows_an_error_when_the_oauth_state_is_invalid(): void
    {
        $socialiteProvider = Mockery::mock(Provider::class);
        $socialiteProvider->shouldReceive('user')
            ->once()
            ->andThrow(new InvalidStateException());

        Socialite::shouldReceive('driver')
            ->once()
            ->with('github')
            ->andReturn($socialiteProvider);

        $this->get('/auth/github/callback')
            ->assertBadRequest()
            ->assertSee('Authentication error');
    }

    private function mockSocialiteUser(string $provider, string $id, string $name, string $email): void
    {
        $socialUser = (new User())->map([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);

        $socialiteProvider = Mockery::mock(Provider::class);
        $socialiteProvider->shouldReceive('user')
            ->once()
            ->andReturn($socialUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with($provider)
            ->andReturn($socialiteProvider);
    }
}
