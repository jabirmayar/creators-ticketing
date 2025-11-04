<?php

namespace daacreators\CreatorsTicketing\Models;

use Illuminate\Database\Eloquent\Model;

class TicketStatus extends Model
{
    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('creators-ticketing.table_prefix') . 'ticket_statuses');
    }
    protected $casts = [
        'is_default_for_new' => 'boolean',
        'is_closing_status' => 'boolean',
    ];
}