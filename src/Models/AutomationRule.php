<?php
namespace daacreators\CreatorsTicketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationRule extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('creators-ticketing.table_prefix') . 'automation_rules');
    }

    protected $casts = [
        'is_active' => 'boolean',
        'stop_processing' => 'boolean',
        'trigger_conditions' => 'array',
        'conditions' => 'array',
        'actions' => 'array',
        'last_triggered_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEvent($query, string $event)
    {
        return $query->where('trigger_event', $event);
    }
}