<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Student',
            'email' => 'test@student.hu.nl',
            'password' => 'geheim1234',
            'password_confirmation' => 'geheim1234',
        ], $overrides);
    }

    /** Registreert en geeft [token, code] terug. */
    private function startRegistration(array $overrides = []): array
    {
        Mail::fake();

        $response = $this->postJson('/api/register', $this->registerPayload($overrides));
        $response->assertStatus(202)->assertJsonPath('verification_required', true);

        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return [$response->json('verification_token'), $code];
    }

    public function test_register_sends_code_and_creates_no_user_yet(): void
    {
        [$token, $code] = $this->startRegistration();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertDatabaseMissing('users', ['email' => 'test@student.hu.nl']);
        $this->assertDatabaseHas('email_verifications', ['email' => 'test@student.hu.nl']);
    }

    public function test_code_is_not_stored_in_plain_text(): void
    {
        [, $code] = $this->startRegistration();

        $row = EmailVerification::where('email', 'test@student.hu.nl')->first();
        $this->assertNotSame($code, $row->code_hash);
    }

    public function test_correct_code_creates_verified_user_and_returns_token(): void
    {
        [$token, $code] = $this->startRegistration();

        $this->postJson('/api/verify-email', [
            'email' => 'test@student.hu.nl',
            'code' => $code,
            'verification_token' => $token,
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        $user = User::where('email', 'test@student.hu.nl')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->profile);
        $this->assertDatabaseMissing('email_verifications', ['email' => 'test@student.hu.nl']);

        // Het wachtwoord uit de registratie werkt om in te loggen.
        $this->postJson('/api/login', [
            'email' => 'test@student.hu.nl',
            'password' => 'geheim1234',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_wrong_code_is_rejected_and_attempts_are_limited(): void
    {
        [$token, $code] = $this->startRegistration();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/verify-email', [
                'email' => 'test@student.hu.nl',
                'code' => $wrong,
                'verification_token' => $token,
            ])->assertStatus(422)->assertJsonPath('reason', 'invalid_code');
        }

        // 5e foute poging: code is geblokkeerd.
        $this->postJson('/api/verify-email', [
            'email' => 'test@student.hu.nl',
            'code' => $wrong,
            'verification_token' => $token,
        ])->assertStatus(422)->assertJsonPath('reason', 'too_many_attempts');

        // Ook de juiste code werkt dan niet meer.
        $this->postJson('/api/verify-email', [
            'email' => 'test@student.hu.nl',
            'code' => $code,
            'verification_token' => $token,
        ])->assertStatus(422)->assertJsonPath('reason', 'too_many_attempts');

        $this->assertDatabaseMissing('users', ['email' => 'test@student.hu.nl']);
    }

    public function test_expired_code_is_rejected(): void
    {
        [$token, $code] = $this->startRegistration();

        $this->travel(16)->minutes();

        $this->postJson('/api/verify-email', [
            'email' => 'test@student.hu.nl',
            'code' => $code,
            'verification_token' => $token,
        ])->assertStatus(422)->assertJsonPath('reason', 'expired');
    }

    public function test_wrong_token_cannot_verify(): void
    {
        [, $code] = $this->startRegistration();

        $this->postJson('/api/verify-email', [
            'email' => 'test@student.hu.nl',
            'code' => $code,
            'verification_token' => 'niet-het-juiste-token',
        ])->assertStatus(422)->assertJsonPath('reason', 'session_invalid');

        $this->assertDatabaseMissing('users', ['email' => 'test@student.hu.nl']);
    }

    public function test_second_registration_cannot_hijack_a_pending_one(): void
    {
        [$victimToken, $victimCode] = $this->startRegistration();

        // Een tweede registratie voor hetzelfde adres wordt tijdens de wachttijd geweigerd.
        $this->postJson('/api/register', $this->registerPayload(['password' => 'aanvaller123', 'password_confirmation' => 'aanvaller123']))
            ->assertStatus(429)->assertJsonPath('reason', 'cooldown');

        // Ook na de wachttijd: het oude token werkt niet meer, dus de code uit de nieuwe mail
        // is niet te gebruiken met het token van de eerste registratie.
        $this->travel(61)->seconds();
        Mail::fake();
        $this->postJson('/api/register', $this->registerPayload(['password' => 'aanvaller123', 'password_confirmation' => 'aanvaller123']))
            ->assertStatus(202);

        $newCode = null;
        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$newCode) {
            $newCode = $mail->code;

            return true;
        });

        $this->postJson('/api/verify-email', [
            'email' => 'test@student.hu.nl',
            'code' => $newCode,
            'verification_token' => $victimToken,
        ])->assertStatus(422)->assertJsonPath('reason', 'session_invalid');

        $this->assertDatabaseMissing('users', ['email' => 'test@student.hu.nl']);
    }

    public function test_resend_respects_cooldown_and_sends_new_code(): void
    {
        [$token] = $this->startRegistration();

        $this->postJson('/api/verify-email/resend', [
            'email' => 'test@student.hu.nl',
            'verification_token' => $token,
        ])->assertStatus(429)->assertJsonPath('reason', 'cooldown');

        $this->travel(61)->seconds();
        Mail::fake();

        $this->postJson('/api/verify-email/resend', [
            'email' => 'test@student.hu.nl',
            'verification_token' => $token,
        ])->assertOk();

        Mail::assertSent(VerificationCodeMail::class, 1);
    }

    public function test_resend_with_wrong_token_sends_nothing(): void
    {
        $this->startRegistration();
        $this->travel(61)->seconds();
        Mail::fake();

        $this->postJson('/api/verify-email/resend', [
            'email' => 'test@student.hu.nl',
            'verification_token' => 'fout',
        ])->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_register_rejects_non_hu_domain(): void
    {
        Mail::fake();

        $this->postJson('/api/register', $this->registerPayload(['email' => 'iemand@gmail.com']))
            ->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_unverified_existing_user_must_verify_on_login(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'oud@student.hu.nl',
            'password' => 'geheim1234',
        ]);

        Mail::fake();

        $response = $this->postJson('/api/login', [
            'email' => 'oud@student.hu.nl',
            'password' => 'geheim1234',
        ])->assertStatus(403)->assertJsonPath('verification_required', true);

        $this->assertArrayNotHasKey('token', $response->json());

        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->postJson('/api/verify-email', [
            'email' => 'oud@student.hu.nl',
            'code' => $code,
            'verification_token' => $response->json('verification_token'),
        ])->assertOk()->assertJsonStructure(['token']);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_wrong_password_does_not_trigger_verification_mail(): void
    {
        User::factory()->unverified()->create([
            'email' => 'oud@student.hu.nl',
            'password' => 'geheim1234',
        ]);

        Mail::fake();

        $this->postJson('/api/login', [
            'email' => 'oud@student.hu.nl',
            'password' => 'verkeerd-wachtwoord',
        ])->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_unverified_token_cannot_use_protected_routes(): void
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('web')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/events')
            ->assertStatus(403)
            ->assertJsonPath('verification_required', true);

        // Uitloggen en de eigen status lezen blijft mogelijk.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertOk();
    }

    public function test_verified_user_can_use_protected_routes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/events')
            ->assertOk();
    }
}
