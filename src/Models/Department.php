<?php

namespace daacreators\CreatorsTicketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Department extends Model
{
    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('creators-ticketing.table_prefix') . 'departments');
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

   public function agents(): BelongsToMany
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        $pivot = config('creators-ticketing.table_prefix') . 'department_users';
        
        $userInstance = new $userModel;
        $userPrimaryKey = $userInstance->getKeyName();

        return $this->belongsToMany(
            $userModel, 
            $pivot, 
            'department_id',              
            'user_id',    
            'id',                         
            $userPrimaryKey                
        )->withPivot([
            'role',
            'can_create_tickets',
            'can_view_all_tickets',
            'can_assign_tickets',
            'can_change_departments',
            'can_change_status',
            'can_change_priority',
            'can_delete_tickets',
            'can_reply_to_tickets',
            'can_add_internal_notes',
            'can_view_internal_notes',
            'can_manage_automations',
            'can_view_automation_logs',
        ]);
    }

    public function forms(): BelongsToMany
    {
    return $this->belongsToMany(Form::class, config('creators-ticketing.table_prefix') . 'department_forms');
    }

    public function form()
    {
        return $this->forms()->first();
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public')->where('is_active', true);
    }

    public function scopeInternal($query)
    {
        return $query->where('visibility', 'internal')->where('is_active', true);
    }
}