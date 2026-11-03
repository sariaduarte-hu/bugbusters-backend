<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollVote extends Model
{
    protected $fillable = [
        'poll_id',
        'poll_option_id',
        'user_id',
    ];

        public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
