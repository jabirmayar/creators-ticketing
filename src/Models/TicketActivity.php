<?php

namespace daacreators\CreatorsTicketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketActivity extends Model
{
    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('creators-ticketing.table_prefix') . 'ticket_activities');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);

        return $this->belongsTo($userModel, "user_id");
    }
}