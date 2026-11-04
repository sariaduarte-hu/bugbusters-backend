<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailVerification extends Model
{
    protected $fillable = [
        'email',
        'name',
        'password',
        'code_hash',
        'token_hash',
        'attempts',
        'expires_at',
        'sent_at',
    ];

    protected $hidden = ['password', 'code_hash', 'token_hash'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /** Hoort bij een nieuwe registratie (account bestaat nog niet). */
    public function isPendingRegistration(): bool
    {
        return $this->name !== null;
    }
}
