<?php

namespace daacreators\CreatorsTicketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketReply extends Model
{
    protected $guarded = [];
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('creators-ticketing.table_prefix') . 'ticket_replies');
    }
    protected $casts = [
        'is_internal_note' => 'boolean',
        'is_seen' => 'boolean',
        'seen_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        return $this->belongsTo($userModel, 'user_id');
    }

    public function markSeenBy($userId): void
    {
        $this->is_seen = true;
        $this->seen_by = $userId;
        $this->seen_at = now();
        $this->saveQuietly();
    }

}