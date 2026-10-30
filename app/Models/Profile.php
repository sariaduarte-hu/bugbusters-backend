<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    protected $fillable = [
        'user_id',
        'age',
        'birthday',
        'about',
        'hobbies',
    ];

    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'birthday' => 'date:Y-m-d',
            'hobbies' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
