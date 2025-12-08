<?php

namespace daacreators\CreatorsTicketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use daacreators\CreatorsTicketing\Enums\TicketPriority;

class Ticket extends Model
{
    protected $guarded = [];

    protected $casts = [
        'custom_fields' => 'array',
        'last_activity_at' => 'datetime',
        'priority' => TicketPriority::class,
        'is_seen' => 'boolean',
        'seen_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (empty($ticket->last_activity_at)) {
                $ticket->last_activity_at = now();
            }
        });

        static::deleted(function (Ticket $ticket) {
            Storage::disk('private')->deleteDirectory('ticket-attachments/' . $ticket->id);
        });
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('creators-ticketing.table_prefix') . 'tickets');
    }

    public function requester(): BelongsTo
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        return $this->belongsTo($userModel, 'user_id');
    }

    public function assignee(): BelongsTo
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        return $this->belongsTo($userModel, 'assignee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'ticket_status_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    public function publicReplies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->where('is_internal_note', false);
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(TicketReply::class)->where('is_internal_note', true);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class);
    }

    public function markSeenBy($userId): void
    {
        if ($userId == $this->user_id) {
            return;
        }

        if ($this->is_seen) {
            return;
        }

        $this->is_seen = true;
        $this->seen_by = $userId;
        $this->seen_at = now();
        $this->saveQuietly();
    }

    public function markUnseen(): void
    {
        $this->is_seen = false;
        $this->seen_by = null;
        $this->seen_at = null;
        $this->saveQuietly();
    }

    public function isUnseen(): bool
    {
        return !$this->is_seen;
    }
    
    public function getCustomField(string $fieldName)
    {
        return $this->custom_fields[$fieldName] ?? null;
    }

    public function setCustomField(string $fieldName, $value): void
    {
        $customFields = $this->custom_fields ?? [];
        $customFields[$fieldName] = $value;
        $this->custom_fields = $customFields;
    }

    public static function scopeForUser($query, $userId)
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        $user = $userModel::find($userId);

        if (!$user) {
            return $query->where('id', null);
        }

        $field = config('creators-ticketing.navigation_visibility.field', 'email');
        $allowed = config('creators-ticketing.navigation_visibility.allowed', []);

        if (in_array($user->{$field} ?? null, $allowed, true)) {
            return $query;
        }

        $departmentIds = \DB::table(config('creators-ticketing.table_prefix') . 'department_users')
            ->where('user_id', $userId)
            ->pluck('department_id')
            ->toArray();

        if (empty($departmentIds)) {
            return $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhere('assignee_id', $userId);
            });
        }

        $canViewAllDepartments = \DB::table(config('creators-ticketing.table_prefix') . 'department_users')
            ->where('user_id', $userId)
            ->where('can_view_all_tickets', true)
            ->pluck('department_id')
            ->toArray();

        return $query->where(function ($q) use ($userId, $departmentIds, $canViewAllDepartments) {
            $q->where('user_id', $userId)
              ->orWhere('assignee_id', $userId)
              ->orWhereIn('department_id', $canViewAllDepartments);
        });
    }

    protected function title(): Attribute
    {
        return Attribute::make(
            get: function () {
                $form = $this->department?->forms()->with('fields')->first();
                
                if (!$form || !$this->custom_fields) {
                    return 'Ticket #' . $this->ticket_uid;
                }

                $titleField = $form->fields->first(function ($field) {
                    return in_array(strtolower($field->name), ['title', 'subject', 'issue_title', 'ticket_title']);
                });

                if ($titleField && isset($this->custom_fields[$titleField->name])) {
                    return $this->custom_fields[$titleField->name];
                }

                $firstTextField = $form->fields->first(function ($field) {
                    return in_array($field->type, ['text', 'textarea']);
                });

                if ($firstTextField && isset($this->custom_fields[$firstTextField->name])) {
                    $value = $this->custom_fields[$firstTextField->name];
                    return is_string($value) ? substr(strip_tags($value), 0, 100) : 'Ticket #' . $this->ticket_uid;
                }

                return 'Ticket #' . $this->ticket_uid;
            }
        );
    }

    protected function content(): Attribute
    {
        return Attribute::make(
            get: function () {
                $form = $this->department?->forms()->with('fields')->first();
                
                if (!$form || !$this->custom_fields) {
                    return '';
                }

                $contentField = $form->fields->first(function ($field) {
                    return in_array(strtolower($field->name), ['content', 'description', 'details', 'message', 'issue_description']);
                });

                if ($contentField && isset($this->custom_fields[$contentField->name])) {
                    return $this->custom_fields[$contentField->name];
                }

                $firstTextareaField = $form->fields->first(function ($field) {
                    return $field->type === 'textarea';
                });

                if ($firstTextareaField && isset($this->custom_fields[$firstTextareaField->name])) {
                    return $this->custom_fields[$firstTextareaField->name];
                }

                return '';
            }
        );
    }
}