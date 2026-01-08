<?php

namespace daacreators\CreatorsTicketing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\AutomationRule;
use daacreators\CreatorsTicketing\Models\AutomationLog;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use daacreators\CreatorsTicketing\Support\UserNameResolver;

class AutomationService
{
    public function processAutomations(Ticket $ticket, string $event, array $context = []): void
    {
        if ($ticket->exists) {
            $ticket->refresh();
            $ticket->loadMissing(['department', 'status', 'requester', 'assignee', 'replies']);
        }

        $rules = AutomationRule::active()
            ->forEvent($event)
            ->orderBy('execution_order')
            ->get();

        foreach ($rules as $rule) {
            try {
                if ($this->evaluateConditions($ticket, $rule, $context)) {
                    $this->executeActions($ticket, $rule, $context);
                    
                    if ($rule->stop_processing) {
                        break;
                    }
                }
            } catch (\Exception $e) {
                Log::error("Automation rule {$rule->id} failed", [
                    'rule_id' => $rule->id,
                    'ticket_id' => $ticket->id,
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
                
                $this->logExecution($rule, $ticket, $event, [], [], false, $e->getMessage());
            }
        }
    }

    protected function evaluateConditions(Ticket $ticket, AutomationRule $rule, array $context): bool
    {
        $conditions = $rule->conditions;
        
        // If conditions is null or strictly empty, allow the rule
        if (empty($conditions)) {
            return true;
        }

        $conditionsMet = [];

        // Helper to clean arrays (remove nulls, empty strings)
        $clean = fn($val) => is_array($val) ? array_filter($val, fn($v) => !is_null($v) && $v !== '') : $val;

        // Check department
        $deptIds = $clean($conditions['department_id'] ?? []);
        if (!empty($deptIds)) {
            if (is_array($deptIds)) {
                $conditionsMet['department'] = in_array($ticket->department_id, $deptIds);
            } else {
                $conditionsMet['department'] = $ticket->department_id == $deptIds;
            }
        }

        // Check form
        $formIds = $clean($conditions['form_id'] ?? []);
        if (!empty($formIds)) {
            if (is_array($formIds)) {
                $conditionsMet['form'] = in_array($ticket->form_id, $formIds);
            } else {
                $conditionsMet['form'] = $ticket->form_id == $formIds;
            }
        }

        // Check priority
        $priorities = $clean($conditions['priority'] ?? []);
        if (!empty($priorities)) {
            if (is_array($priorities)) {
                $conditionsMet['priority'] = in_array($ticket->priority->value, $priorities);
            } else {
                $conditionsMet['priority'] = $ticket->priority->value == $priorities;
            }
        }

        // Check status
        $statusIds = $clean($conditions['status_id'] ?? []);
        if (!empty($statusIds)) {
            if (is_array($statusIds)) {
                $conditionsMet['status'] = in_array($ticket->ticket_status_id, $statusIds);
            } else {
                $conditionsMet['status'] = $ticket->ticket_status_id == $statusIds;
            }
        }

        // Check assignee
        $assignees = $clean($conditions['assignee_id'] ?? []);
        if (!empty($assignees)) {
            // Handle special string values 'unassigned' / 'assigned'
            $isUnassignedCheck = in_array('unassigned', is_array($assignees) ? $assignees : [$assignees]);
            $isAssignedCheck = in_array('assigned', is_array($assignees) ? $assignees : [$assignees]);

            if ($isUnassignedCheck) {
                $conditionsMet['assignee'] = $ticket->assignee_id === null;
            } elseif ($isAssignedCheck) {
                $conditionsMet['assignee'] = $ticket->assignee_id !== null;
            } elseif (is_array($assignees)) {
                $conditionsMet['assignee'] = in_array($ticket->assignee_id, $assignees);
            } else {
                $conditionsMet['assignee'] = $ticket->assignee_id == $assignees;
            }
        }

        // Check requester
        $requesters = $clean($conditions['requester_id'] ?? []);
        if (!empty($requesters)) {
            if (is_array($requesters)) {
                $conditionsMet['requester'] = in_array($ticket->user_id, $requesters);
            } else {
                $conditionsMet['requester'] = $ticket->user_id == $requesters;
            }
        }

        // Check custom fields
        if (!empty($conditions['custom_fields']) && is_array($conditions['custom_fields'])) {
            foreach ($conditions['custom_fields'] as $fieldName => $expectedValue) {
                $actualValue = $ticket->custom_fields[$fieldName] ?? null;
                $expectedValue = $clean($expectedValue);
                
                if (is_array($expectedValue)) {
                    $conditionsMet["custom_field_{$fieldName}"] = in_array($actualValue, $expectedValue);
                } else {
                    $conditionsMet["custom_field_{$fieldName}"] = $actualValue == $expectedValue;
                }
            }
        }

        // Check tags
        $tags = $clean($conditions['tags'] ?? []);
        if (!empty($tags)) {
            $ticketTags = $ticket->tags ?? [];
            $requiredTags = is_array($tags) ? $tags : [$tags];
            
            if (isset($conditions['tags_match_type']) && $conditions['tags_match_type'] === 'any') {
                $conditionsMet['tags'] = !empty(array_intersect($ticketTags, $requiredTags));
            } else {
                $conditionsMet['tags'] = empty(array_diff($requiredTags, $ticketTags));
            }
        }

        // Check time-based conditions
        if (!empty($conditions['created_within_hours'])) {
            $hoursAgo = now()->subHours($conditions['created_within_hours']);
            $conditionsMet['created_within'] = $ticket->created_at->greaterThan($hoursAgo);
        }

        if (!empty($conditions['last_activity_within_hours'])) {
            $hoursAgo = now()->subHours($conditions['last_activity_within_hours']);
            $conditionsMet['activity_within'] = $ticket->last_activity_at && 
                $ticket->last_activity_at->greaterThan($hoursAgo);
        }

        // Context: Old Status
        $oldStatusIds = $clean($conditions['old_status_id'] ?? []);
        if (!empty($oldStatusIds) && isset($context['old_status_id'])) {
            if (is_array($oldStatusIds)) {
                $conditionsMet['old_status'] = in_array($context['old_status_id'], $oldStatusIds);
            } else {
                $conditionsMet['old_status'] = $context['old_status_id'] == $oldStatusIds;
            }
        }

        // Context: Old Priority
        $oldPriorities = $clean($conditions['old_priority'] ?? []);
        if (!empty($oldPriorities) && isset($context['old_priority'])) {
            if (is_array($oldPriorities)) {
                $conditionsMet['old_priority'] = in_array($context['old_priority'], $oldPriorities);
            } else {
                $conditionsMet['old_priority'] = $context['old_priority'] == $oldPriorities;
            }
        }

        // If $conditionsMet is empty, it means no conditions were actually active/set,
        // so the rule should match (wildcard behavior).
        return empty($conditionsMet) || !in_array(false, $conditionsMet, true);
    }

    protected function executeActions(Ticket $ticket, AutomationRule $rule, array $context): void
    {
        $actions = $rule->actions;
        $actionsPerformed = [];

        DB::beginTransaction();
        
        try {
            $updates = [];

            if (!empty($actions['assign_to'])) {
                $assigneeId = $this->resolveAssignee($actions['assign_to'], $ticket);
                if ($assigneeId !== $ticket->assignee_id) {
                    $updates['assignee_id'] = $assigneeId;
                    $actionsPerformed['assigned_to'] = $assigneeId;
                }
            }

            if (!empty($actions['set_status']) && $actions['set_status'] != $ticket->ticket_status_id) {
                $updates['ticket_status_id'] = $actions['set_status'];
                $actionsPerformed['status_changed'] = $actions['set_status'];
            }

            if (!empty($actions['set_priority'])) {
                $priority = TicketPriority::from($actions['set_priority']);
                if ($priority !== $ticket->priority) {
                    $updates['priority'] = $priority;
                    $actionsPerformed['priority_changed'] = $actions['set_priority'];
                }
            }

            if (!empty($actions['transfer_to_department']) && 
                $actions['transfer_to_department'] != $ticket->department_id) {
                $updates['department_id'] = $actions['transfer_to_department'];
                $actionsPerformed['department_changed'] = $actions['transfer_to_department'];
            }

            if (!empty($actions['add_tags'])) {
                $tagsToAdd = is_array($actions['add_tags']) ? $actions['add_tags'] : [$actions['add_tags']];
                $currentTags = $ticket->tags ?? [];
                $newTags = array_unique(array_merge($currentTags, $tagsToAdd));
                $updates['tags'] = $newTags;
                $actionsPerformed['tags_added'] = $tagsToAdd;
            }

            if (!empty($actions['remove_tags'])) {
                $tagsToRemove = is_array($actions['remove_tags']) ? $actions['remove_tags'] : [$actions['remove_tags']];
                $currentTags = $ticket->tags ?? [];
                $newTags = array_diff($currentTags, $tagsToRemove);
                $updates['tags'] = array_values($newTags);
                $actionsPerformed['tags_removed'] = $tagsToRemove;
            }

            if (!empty($updates)) {
                $ticket->update($updates);
            }

            if (!empty($actions['add_internal_note'])) {
                $note = $ticket->replies()->create([
                    'content' => $this->replacePlaceholders($actions['add_internal_note'], $ticket, $rule),
                    'user_id' => 1, // System user or admin
                    'is_internal_note' => true,
                ]);
                $actionsPerformed['internal_note_added'] = $note->id;
            }

            if (!empty($actions['add_public_reply'])) {
                $reply = $ticket->replies()->create([
                    'content' => $this->replacePlaceholders($actions['add_public_reply'], $ticket, $rule),
                    'user_id' => 1, // System user or admin
                    'is_internal_note' => false,
                ]);
                $actionsPerformed['public_reply_added'] = $reply->id;
            }

            $rule->increment('times_triggered');
            $rule->update(['last_triggered_at' => now()]);

            $this->logExecution($rule, $ticket, $rule->trigger_event, 
                $rule->conditions, $actionsPerformed, true);

            $ticket->activities()->create([
                'user_id' => null,
                'description' => 'Automation rule executed',
                'new_value' => $rule->name,
            ]);

            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function resolveAssignee($assignTo, Ticket $ticket)
    {
        if ($assignTo === 'unassigned') {
            return null;
        }

        if ($assignTo === 'round_robin') {
            return $this->getRoundRobinAssignee($ticket->department_id);
        }

        if ($assignTo === 'least_loaded') {
            return $this->getLeastLoadedAssignee($ticket->department_id);
        }

        if (is_numeric($assignTo)) {
            return $assignTo;
        }

        return null;
    }

    protected function getRoundRobinAssignee(int $departmentId): ?int
    {
        $agents = DB::table(config('creators-ticketing.table_prefix') . 'department_users')
            ->where('department_id', $departmentId)
            ->where('can_reply_to_tickets', true)
            ->pluck('user_id')
            ->toArray();

        if (empty($agents)) {
            return null;
        }

        $lastAssigned = DB::table(config('creators-ticketing.table_prefix') . 'tickets')
            ->where('department_id', $departmentId)
            ->whereNotNull('assignee_id')
            ->latest('created_at')
            ->value('assignee_id');

        if (!$lastAssigned || !in_array($lastAssigned, $agents)) {
            return $agents[0];
        }

        $currentIndex = array_search($lastAssigned, $agents);
        $nextIndex = ($currentIndex + 1) % count($agents);
        
        return $agents[$nextIndex];
    }

    protected function getLeastLoadedAssignee(int $departmentId): ?int
    {
        $agents = DB::table(config('creators-ticketing.table_prefix') . 'department_users')
            ->where('department_id', $departmentId)
            ->where('can_reply_to_tickets', true)
            ->pluck('user_id')
            ->toArray();

        if (empty($agents)) {
            return null;
        }

        $ticketCounts = DB::table(config('creators-ticketing.table_prefix') . 'tickets as t')
            ->join(config('creators-ticketing.table_prefix') . 'ticket_statuses as ts', 't.ticket_status_id', '=', 'ts.id')
            ->whereIn('t.assignee_id', $agents)
            ->where('ts.is_closing_status', false)
            ->select('t.assignee_id', DB::raw('count(*) as ticket_count'))
            ->groupBy('t.assignee_id')
            ->pluck('ticket_count', 'assignee_id')
            ->toArray();

        $leastLoaded = null;
        $minCount = PHP_INT_MAX;

        foreach ($agents as $agentId) {
            $count = $ticketCounts[$agentId] ?? 0;
            if ($count < $minCount) {
                $minCount = $count;
                $leastLoaded = $agentId;
            }
        }

        return $leastLoaded;
    }

    protected function replacePlaceholders(string $text, Ticket $ticket, AutomationRule $rule): string
    {
        $replacements = [
            '{ticket_id}' => $ticket->ticket_uid ?? $ticket->id,
            '{ticket_title}' => $ticket->title ?? '',
            '{department}' => $ticket->department?->name ?? 'Unknown Department',
            '{requester}' => $ticket->requester ? UserNameResolver::resolve($ticket->requester) : 'Unknown User',
            '{assignee}' => $ticket->assignee ? UserNameResolver::resolve($ticket->assignee) : 'Unassigned',
            '{status}' => $ticket->status?->name ?? 'Unknown Status',
            '{priority}' => $ticket->priority?->getLabel() ?? 'Unknown Priority',
            '{rule_name}' => $rule->name,
            '{current_date}' => now()->format('Y-m-d'),
            '{current_time}' => now()->format('H:i:s'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    protected function logExecution(
        AutomationRule $rule,
        Ticket $ticket,
        string $event,
        array $conditionsMet,
        array $actionsPerformed,
        bool $success,
        ?string $errorMessage = null
    ): void {
        AutomationLog::create([
            'automation_rule_id' => $rule->id,
            'ticket_id' => $ticket->id,
            'trigger_event' => $event,
            'conditions_met' => $conditionsMet,
            'actions_performed' => $actionsPerformed,
            'success' => $success,
            'error_message' => $errorMessage,
        ]);
    }
}