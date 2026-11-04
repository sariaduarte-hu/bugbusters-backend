<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    protected $fillable = [
        'title',
        'start',
        'end',
        'description',
        'user_id',
    ];

    protected $hidden = ['user_id'];

    protected $appends = ['is_owner'];

    protected function casts(): array
    {
        return [
            'start' => 'datetime',
            'end' => 'datetime',
        ];
    }

    // Alleen de maker mag een evenement verwijderen.
    protected function isOwner(): Attribute
    {
        return Attribute::get(
            fn () => $this->user_id !== null && $this->user_id === auth()->id()
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_signups')->withTimestamps();
    }
}
