<?php

namespace App\Services;

use App\Exceptions\VerificationFailed;
use App\Mail\VerificationCodeMail;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * 6-cijferige e-mailverificatie.
 *
 * Er zijn twee situaties:
 *  1. Nieuwe registratie: er wordt nog GEEN gebruiker aangemaakt. Naam en (gehasht) wachtwoord
 *     staan tijdelijk in email_verifications. Pas na de juiste code ontstaat het account.
 *  2. Bestaand account zonder bevestigd adres (van voor deze functie): bij een geslaagde login
 *     krijgt de gebruiker dezelfde popup, en na de code wordt email_verified_at gezet.
 *
 * Bij beide krijgt de browser een geheim "verification_token". Verifiëren en opnieuw sturen
 * kan alleen met dat token. Dat voorkomt dat een ander (door dezelfde registratie te starten)
 * jouw lopende verificatie kan overnemen of jouw inbox kan volspammen.
 */
class EmailVerificationService
{
    public const CODE_TTL_MINUTES = 15;
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const MAX_ATTEMPTS_PER_CODE = 5;
    public const MAX_ATTEMPTS_PER_HOUR = 10;

    /**
     * Start een nieuwe registratie en stuur de code. Geeft het verification_token terug.
     */
    public function beginRegistration(string $name, string $email, string $passwordHash): string
    {
        // Oude, nooit afgeronde registraties opruimen.
        EmailVerification::where('expires_at', '<', now()->subDay())->delete();

        $row = EmailVerification::where('email', $email)->first();

        if ($row && ($left = $this->cooldownLeft($row)) > 0) {
            throw new VerificationFailed(
                'Er is net al een code gestuurd. Wacht even voordat je het opnieuw probeert.',
                429,
                'cooldown',
                $left,
            );
        }

        $row ??= new EmailVerification(['email' => $email]);
        $row->fill(['name' => $name, 'password' => $passwordHash]);

        $token = Str::random(40);
        $row->token_hash = hash('sha256', $token);

        $this->deliver($row, $name);

        return $token;
    }

    /**
     * Bestaand, nog niet bevestigd account (na een geslaagde login). Geeft het
     * verification_token terug. Stuurt alleen een nieuwe code als de vorige niet net is gestuurd.
     */
    public function beginForExistingUser(User $user): string
    {
        $row = EmailVerification::where('email', $user->email)->first()
            ?? new EmailVerification(['email' => $user->email]);

        $row->fill(['name' => null, 'password' => null]);

        $token = Str::random(40);
        $row->token_hash = hash('sha256', $token);

        if ($row->exists && $this->cooldownLeft($row) > 0) {
            // De net gestuurde code blijft geldig; alleen het token is nieuw.
            $row->save();

            return $token;
        }

        $this->deliver($row, $user->name);

        return $token;
    }

    /**
     * Stuur een nieuwe code. Geeft het aantal seconden terug tot de volgende keer mag.
     */
    public function resend(string $email, string $token): int
    {
        $row = $this->findForToken($email, $token);

        if (($left = $this->cooldownLeft($row)) > 0) {
            throw new VerificationFailed(
                'Je kunt over '.$left.' seconden een nieuwe code aanvragen.',
                429,
                'cooldown',
                $left,
            );
        }

        $name = $row->name ?? (string) User::where('email', $email)->value('name');

        $this->deliver($row, $name);

        return self::RESEND_COOLDOWN_SECONDS;
    }

    /**
     * Controleer de code. Bij succes bestaat het account en is het adres bevestigd.
     */
    public function verify(string $email, string $code, string $token): User
    {
        $row = $this->findForToken($email, $token);

        $limiterKey = $this->limiterKey($email);

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_ATTEMPTS_PER_HOUR)) {
            $seconds = RateLimiter::availableIn($limiterKey);

            throw new VerificationFailed(
                'Te veel pogingen. Probeer het over '.max(1, (int) ceil($seconds / 60)).' minuten opnieuw.',
                429,
                'rate_limited',
                $seconds,
            );
        }

        if ($row->expires_at->isPast()) {
            throw new VerificationFailed('Deze code is verlopen. Vraag een nieuwe code aan.', 422, 'expired');
        }

        if ($row->attempts >= self::MAX_ATTEMPTS_PER_CODE) {
            throw new VerificationFailed('Te veel foute pogingen. Vraag een nieuwe code aan.', 422, 'too_many_attempts');
        }

        if (! hash_equals($row->code_hash, $this->hashCode($email, $code))) {
            $row->increment('attempts');
            RateLimiter::hit($limiterKey, 3600);

            $left = self::MAX_ATTEMPTS_PER_CODE - $row->attempts;

            if ($left <= 0) {
                throw new VerificationFailed('Te veel foute pogingen. Vraag een nieuwe code aan.', 422, 'too_many_attempts');
            }

            throw new VerificationFailed(
                'De code klopt niet. Je hebt nog '.$left.($left === 1 ? ' poging.' : ' pogingen.'),
                422,
                'invalid_code',
            );
        }

        try {
            $user = DB::transaction(function () use ($row, $email) {
                if ($row->isPendingRegistration()) {
                    $user = new User([
                        'name' => $row->name,
                        'email' => $email,
                        'password' => $row->password, // al gehasht; de 'hashed' cast laat dat zo
                    ]);
                    $user->forceFill(['email_verified_at' => now()])->save();
                    $user->profile()->create();
                } else {
                    $user = User::where('email', $email)->lockForUpdate()->first();

                    if (! $user) {
                        throw new VerificationFailed('Dit account bestaat niet meer.', 422, 'session_invalid');
                    }

                    if ($user->email_verified_at === null) {
                        $user->forceFill(['email_verified_at' => now()])->save();
                    }
                }

                $row->delete();

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // Ondertussen is er al een account met dit adres aangemaakt.
            $row->delete();

            throw new VerificationFailed('Dit e-mailadres is al in gebruik. Log in met je account.', 422, 'session_invalid');
        }

        RateLimiter::clear($limiterKey);

        return $user;
    }

    /**
     * Maak een nieuwe code, verstuur hem en bewaar pas daarna. Mislukt de mail,
     * dan verandert er niets in de database.
     */
    private function deliver(EmailVerification $row, string $name): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            Mail::to($row->email)->send(new VerificationCodeMail($name, $code, self::CODE_TTL_MINUTES));
        } catch (Throwable $e) {
            Log::error('Verificatiemail versturen mislukt: '.$e->getMessage());

            throw new VerificationFailed(
                'We konden de e-mail niet versturen. Probeer het later opnieuw.',
                503,
                'mail_failed',
            );
        }

        $row->forceFill([
            'code_hash' => $this->hashCode($row->email, $code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'sent_at' => now(),
        ])->save();
    }

    private function findForToken(string $email, string $token): EmailVerification
    {
        $row = EmailVerification::where('email', $email)->first();

        if (! $row || ! hash_equals($row->token_hash, hash('sha256', $token))) {
            throw new VerificationFailed(
                'Deze verificatie is niet meer geldig. Log opnieuw in of registreer opnieuw.',
                422,
                'session_invalid',
            );
        }

        return $row;
    }

    private function cooldownLeft(EmailVerification $row): int
    {
        $elapsed = now()->timestamp - $row->sent_at->timestamp;

        return max(0, self::RESEND_COOLDOWN_SECONDS - $elapsed);
    }

    private function hashCode(string $email, string $code): string
    {
        return hash_hmac('sha256', $email.'|'.$code, (string) config('app.key'));
    }

    private function limiterKey(string $email): string
    {
        return 'verify-email:'.sha1($email);
    }
}
