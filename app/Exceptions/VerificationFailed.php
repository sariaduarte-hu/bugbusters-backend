<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Fout tijdens e-mailverificatie. Wordt automatisch als JSON teruggegeven.
 *
 * reason (voor de frontend):
 *  invalid_code      code klopt niet (nog pogingen over)
 *  too_many_attempts te veel foute pogingen op deze code, nieuwe code nodig
 *  expired           code is verlopen, nieuwe code nodig
 *  session_invalid   verificatie bestaat niet (meer); opnieuw inloggen/registreren
 *  rate_limited      te veel pogingen per uur
 *  cooldown          nieuwe code kan nog niet (zie retry_after)
 *  mail_failed       mail kon niet worden verstuurd
 */
class VerificationFailed extends Exception
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $reason,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Een foute of verlopen code is normaal gedrag en hoort niet als ERROR
     * (met stacktrace) in laravel.log. Een niet-false waarde = al afgehandeld.
     */
    public function report(): void
    {
    }

    public function render(): JsonResponse
    {
        $body = ['message' => $this->getMessage(), 'reason' => $this->reason];

        if ($this->retryAfter !== null) {
            $body['retry_after'] = $this->retryAfter;
        }

        return response()->json($body, $this->status);
    }
}
