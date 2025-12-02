<?php

namespace daacreators\CreatorsTicketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    protected $fillable = [
        'form_id',
        'name',
        'label',
        'type',
        'options',
        'is_required',
        'is_multiple',
        'help_text',
        'validation_rules',
        'order',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_multiple' => 'boolean',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('creators-ticketing.table_prefix') . 'form_fields';
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}