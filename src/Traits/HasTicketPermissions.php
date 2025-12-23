<?php

namespace daacreators\CreatorsTicketing\Traits;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

trait HasTicketPermissions
{
    protected function getUserPermissions()
    {
        $user = Filament::auth()->user();
        
        if (!$user) {
            return [
                'is_admin' => false,
                'departments' => [],
                'permissions' => [],
            ];
        }

        $field = config('creators-ticketing.navigation_visibility.field', 'email');
        $allowed = config('creators-ticketing.navigation_visibility.allowed', []);
        $isAdmin = in_array($user->{$field} ?? null, $allowed, true);

        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        $userInstance = new $userModel;
        $userKey = $userInstance->getKeyName();
        $pivotUserColumn = 'user_id';

        $departments = DB::table(config('creators-ticketing.table_prefix') . 'department_users')
            ->where($pivotUserColumn, $user->{$userKey})
            ->get();

        $permissions = [];
        foreach ($departments as $dept) {
            $permissions[$dept->department_id] = [
                'role' => $dept->role,
                'can_create_tickets' => $dept->can_create_tickets,
                'can_view_all_tickets' => $dept->can_view_all_tickets,
                'can_assign_tickets' => $dept->can_assign_tickets,
                'can_change_departments' => $dept->can_change_departments,
                'can_change_status' => $dept->can_change_status,
                'can_change_priority' => $dept->can_change_priority,
                'can_delete_tickets' => $dept->can_delete_tickets,
                'can_reply_to_tickets' => $dept->can_reply_to_tickets,
                'can_add_internal_notes' => $dept->can_add_internal_notes,
                'can_view_internal_notes' => $dept->can_view_internal_notes,
                'can_manage_automations' => $dept->can_manage_automations,
                'can_view_automation_logs' => $dept->can_view_automation_logs,
            ];
        }

        return [
            'is_admin' => $isAdmin,
            'departments' => $departments->pluck('department_id')->toArray(),
            'permissions' => $permissions,
        ];
    }

    protected function canUserViewAllTickets(): bool
    {
        $perms = $this->getUserPermissions();
        
        if ($perms['is_admin']) {
            return true;
        }

        foreach ($perms['permissions'] as $permission) {
            if ($permission['can_view_all_tickets'] ?? false) {
                return true;
            }
        }

        return false;
    }

    protected function getUserDepartmentIds(): array
    {
        $perms = $this->getUserPermissions();
        return $perms['departments'];
    }

    
    public static function canAccessNavigation(array $parameters = []): bool
    {
        $instance = new static();
        $perms = $instance->getUserPermissions();

        return $perms['is_admin'] || !empty($perms['departments']);
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return static::canAccessNavigation();
    }

    public static function canAccess(array $parameters = []): bool
    {
        return static::canAccessNavigation();
    }

    public static function userCan(string $permissionKey): bool
    {
        $instance = new static();
        $data = $instance->getUserPermissions();

        if ($data['is_admin']) {
            return true;
        }

        foreach ($data['permissions'] as $deptPerms) {
            if (!empty($deptPerms[$permissionKey])) {
                return true;
            }
        }

        return false;
    }
}