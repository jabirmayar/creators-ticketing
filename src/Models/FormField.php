<?php

namespace daacreators\CreatorsTicketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('creators-ticketing.table_prefix') . 'form_fields');
    }
    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
    ];

    public function form(): BelongsTo
    {
    return $this->belongsTo(Form::class, 'form_id');
    }
}