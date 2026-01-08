<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Tickets\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Facades\Filament;
use Filament\Actions\ActionGroup;
use Illuminate\Support\Facades\DB;
use Filament\Support\Enums\TextSize;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Group;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Component;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use daacreators\CreatorsTicketing\Models\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Form as SchemaForm;
use daacreators\CreatorsTicketing\Models\Department;
use daacreators\CreatorsTicketing\Models\TicketStatus;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use Filament\Schemas\Components\Actions as SchemaActions;
use daacreators\CreatorsTicketing\Events\TicketReplyAdded;
use daacreators\CreatorsTicketing\Events\InternalNoteAdded;
use daacreators\CreatorsTicketing\Events\TicketTransferred;
use daacreators\CreatorsTicketing\Support\TicketFileHelper;
use daacreators\CreatorsTicketing\Support\UserNameResolver;
use daacreators\CreatorsTicketing\Services\AutomationService;
use daacreators\CreatorsTicketing\Traits\HasTicketPermissions;
use daacreators\CreatorsTicketing\Http\Livewire\TicketTimeline;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use daacreators\CreatorsTicketing\Http\Livewire\TicketChatMessages;
use daacreators\CreatorsTicketing\Http\Livewire\TicketAttachmentsDisplay;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\TicketResource;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\RelationManagers\InternalNotesRelationManager;

class ViewTicket extends ViewRecord
{
    use HasTicketPermissions;

    protected static string $resource = TicketResource::class;
    
    public ?array $replyData = [];

    public function mount(int | string $record): void
    {
        parent::mount($record);
        $this->record->markSeenBy(Filament::auth()->user()?->getKey());
        $this->replyData = [
            'content' => '',
            'is_internal_note' => false,
        ];
    }

    public function changeStatus($statusId): void
    {
        $permissions = $this->getUserPermissions();
        $recordDepartmentId = $this->record->department_id;
        $canChangeStatus = $permissions['is_admin'] || 
                           (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_change_status']);

        if (!$canChangeStatus) {
            Notification::make()->title(__('creators-ticketing::resources.ticket.notifications.unauthorized'))->danger()->body(__('creators-ticketing::resources.ticket.notifications.unauthorized_body_status'))->send();
            return;
        }

        $this->record->update(['ticket_status_id' => $statusId]);
        
        Notification::make()->title(__('creators-ticketing::resources.ticket.notifications.status_updated'))->success()->send();
        
        $this->dispatch('$refresh');
    }

    public function changePriority($priority): void
    {
        $permissions = $this->getUserPermissions();
        $recordDepartmentId = $this->record->department_id;
        $canChangePriority = $permissions['is_admin'] || 
                             (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_change_priority']);

        if (!$canChangePriority) {
            Notification::make()->title(__('creators-ticketing::resources.ticket.notifications.unauthorized'))->danger()->body(__('creators-ticketing::resources.ticket.notifications.unauthorized_body_priority'))->send();
            return;
        }

        $oldPriority = $this->record->priority;
        $this->record->update(['priority' => $priority]);
        
        Notification::make()->title(__('creators-ticketing::resources.ticket.notifications.priority_updated'))->success()->send();
        
        $this->dispatch('$refresh');
    }

    public function assignTicket($assigneeId): void
    {
        $permissions = $this->getUserPermissions();
        $user = Filament::auth()->user();
        $recordDepartmentId = $this->record->department_id;
        
        $canAssignTickets = $permissions['is_admin'] || 
                            (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_assign_tickets']);

        if (!$canAssignTickets) {
            Notification::make()->title(__('creators-ticketing::resources.ticket.notifications.unauthorized'))->danger()->body(__('creators-ticketing::resources.ticket.notifications.unauthorized_body_assign'))->send();
            return;
        }

        $oldAssigneeId = $this->record->assignee_id;
        $newAssigneeId = $assigneeId;

        $this->record->update(['assignee_id' => $newAssigneeId]);

        $this->record->activities()->create([
            'user_id' => $user?->getKey(),
            'description' => 'Ticket was assigned',
            'old_value' => $oldAssigneeId ? UserNameResolver::resolve(User::find($oldAssigneeId)) : 'Unassigned',
            'new_value' => $newAssigneeId ? UserNameResolver::resolve(User::find($newAssigneeId)) : 'Unassigned',
        ]);
        
        $this->record->markSeenBy($user?->getKey());
        
        $context = [
            'old_assignee_id' => $oldAssigneeId,
            'new_assignee_id' => $newAssigneeId,
        ];
        app(AutomationService::class)->processAutomations($this->record, 'ticket_assigned', $context);

        Notification::make()->title(__('creators-ticketing::resources.ticket.notifications.assigned'))->success()->send();
        
        $this->dispatch('$refresh');
    }

    public function submitReply(): void
    {
        $permissions = $this->getUserPermissions();
        $user = Filament::auth()->user();
        $recordDepartmentId = $this->record->department_id;

        $canReplyToTickets = $permissions['is_admin'] || 
                            ($this->record->user_id === $user->getKey() && config('creators-ticketing.allow_requester_to_reply', true)) ||
                            (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_reply_to_tickets']);
        
        $canAddInternalNotes = $permissions['is_admin'] || 
                            (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_add_internal_notes']);

        if (!$canReplyToTickets) {
            Notification::make()->title(__('creators-ticketing::resources.ticket.notifications.unauthorized'))->danger()->body(__('creators-ticketing::resources.ticket.notifications.unauthorized_body_reply'))->send();
            return;
        }
        if (($this->replyData['is_internal_note'] ?? false) && !$canAddInternalNotes) {
            Notification::make()->title(__('creators-ticketing::resources.ticket.notifications.unauthorized'))->danger()->body(__('creators-ticketing::resources.ticket.notifications.unauthorized_body_internal'))->send();
            return;
        }

        $data = $this->replyData ?? [];
        
        if (empty(($data['content'] ?? ''))) {
            Notification::make()
                ->title(__('creators-ticketing::resources.ticket.notifications.reply_empty'))
                ->danger()
                ->send();
            return;
        }

        $content = $data['content'];
        $htmlContent = is_array($content) ? $this->convertTiptapToHtml($content) : $content;
        $htmlContent = $this->moveTempFilesToPermanentStorage($htmlContent);

        $reply = $this->record->replies()->create([
            'content' => str($htmlContent)->sanitizeHtml(),
            'user_id' => Filament::auth()->user()?->getKey(),
            'is_internal_note' => $data['is_internal_note'] ?? false,
        ]);

        $this->record->activities()->create([
            'user_id' => Filament::auth()->user()?->getKey(),
            'description' => $data['is_internal_note'] ?? false ? 'Internal note added' : 'Reply sent',
            'new_value' => substr(strip_tags($htmlContent), 0, 100) . '...',
        ]);

        $this->record->touch('last_activity_at');

        $automationEvent = ($data['is_internal_note'] ?? false) ? 'internal_note_added' : 'reply_added';
        $context = [
            'reply_id' => $reply->id,
            'reply_body' => $reply->content,
            'user_id' => $reply->user_id
        ];

        app(AutomationService::class)->processAutomations($this->record, $automationEvent, $context);

        if ($data['is_internal_note'] ?? false) {
            event(new InternalNoteAdded($this->record, $reply));
            $this->dispatch('internal-note-created'); 
        } else {
            event(new TicketReplyAdded($this->record, $reply));
        }

        $this->replyData = [
            'content' => '',
            'is_internal_note' => false,
        ];

        $this->dispatch('$refresh');
        $this->dispatch('activity-added');
    }

    protected function moveTempFilesToPermanentStorage(string $html): string
    {
        $pattern = '/<img[^>]+src=["\']([^"\']*livewire\/preview-file\/[^"\']+)["\']([^>]*)>/i';
        
        return preg_replace_callback($pattern, function ($matches) {
            $fullTag = $matches[0];
            $tempUrl = html_entity_decode($matches[1]);
            $otherAttributes = $matches[2];
            
            if (preg_match('/preview-file\/([^\?]+)/', $tempUrl, $fileMatches)) {
                $tempFileKey = urldecode($fileMatches[1]);
                
                try {
                    $tempFile = TemporaryUploadedFile::createFromLivewire($tempFileKey);
                    
                    if ($tempFile) {
                        $originalFilename = $this->getOriginalFilename($tempFileKey);
                        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'png';
                        
                        $filename = uniqid() . '_' . time() . '.' . $extension;
                        $storagePath = "ticket-attachments/{$this->record->id}/{$filename}";
                        
                        Storage::disk('private')->put(
                            $storagePath,
                            $tempFile->get()
                        );
                        
                        $permanentUrl = url('/private/ticket-attachments/' . $this->record->id . '/' . $filename);
                        
                        return '<img src="' . $permanentUrl . '"' . $otherAttributes . '>';
                    }
                } catch (\Exception $e) {
                }
            }
            
            return $fullTag;
        }, $html);
    }

    protected function getOriginalFilename(string $tempFileKey): string
    {
        if (preg_match('/-meta([^-]+)-/', $tempFileKey, $matches)) {
            $encoded = $matches[1];
            $decoded = base64_decode($encoded);
            return $decoded ?: 'file.png';
        }
        
        return 'file.png';
    }

    protected function convertTiptapToHtml(array $tiptapContent): string
    {
        if (!isset($tiptapContent['content']) || !is_array($tiptapContent['content'])) {
            return '';
        }
        
        $html = '';
        
        foreach ($tiptapContent['content'] as $block) {
            $html .= $this->processTiptapBlock($block);
        }
        
        return $html;
    }

    public function transferTicket($newDepartmentId, $newAssigneeId = null, $keepCurrentAssignee = false): void
    {
        $permissions = $this->getUserPermissions();
        $recordDepartmentId = $this->record->department_id;
        
        $canChangeDepartment = $permissions['is_admin'] || 
                            (isset($permissions['permissions'][$recordDepartmentId]) && 
                            $permissions['permissions'][$recordDepartmentId]['can_change_departments']);

        if (!$canChangeDepartment) {
            Notification::make()
                ->title(__('creators-ticketing::resources.ticket.notifications.unauthorized'))
                ->danger()
                ->body(__('creators-ticketing::resources.ticket.notifications.unauthorized_body_transfer'))
                ->send();
            return;
        }

        $oldDepartment = $this->record->department;
        $newDepartment = Department::find($newDepartmentId);
        $currentAssigneeId = $this->record->assignee_id;

        $updateData = ['department_id' => $newDepartmentId];
        
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        $userInstance = new $userModel;
        $userKey = $userInstance->getKeyName();
        
        if ($newAssigneeId) {
            $updateData['assignee_id'] = $newAssigneeId;
        } elseif ($keepCurrentAssignee && $currentAssigneeId) {
            $isAssigneeInNewDept = DB::table(config('creators-ticketing.table_prefix') . 'department_users')
                ->where('department_id', $newDepartmentId)
                ->where('user_id', $currentAssigneeId)
                ->exists();
            
            if ($isAssigneeInNewDept) {
                $updateData['assignee_id'] = $currentAssigneeId;
            } else {
                $updateData['assignee_id'] = null;
            }
        } else {
            $updateData['assignee_id'] = null;
        }

        $this->record->update($updateData);

        $activityDescription = 'Ticket transferred from ' . $oldDepartment->name . ' to ' . $newDepartment->name;
        $activityNewValue = $newDepartment->name;
        
        if (isset($updateData['assignee_id']) && $updateData['assignee_id']) {
            $newAssignee = $userModel::find($updateData['assignee_id']);
            $activityDescription .= ' and assigned to ' . UserNameResolver::resolve($newAssignee);
            $activityNewValue .= ' (Assigned: ' . UserNameResolver::resolve($newAssignee) . ')';
        } elseif ($currentAssigneeId && !isset($updateData['assignee_id'])) {
            $activityDescription .= ' (assignee removed - not in new department)';
        }

        $this->record->activities()->create([
            'user_id' => Filament::auth()->user()?->getKey(),
            'description' => 'Ticket transferred',
            'old_value' => $oldDepartment->name,
            'new_value' => $activityNewValue,
        ]);

        event(new TicketTransferred(
            $this->record,
            $oldDepartment,
            $newDepartment,
            Filament::auth()->user()
        ));

        Notification::make()
            ->title(__('creators-ticketing::resources.ticket.notifications.transferred'))
            ->success()
            ->body($activityDescription)
            ->send();

        $this->dispatch('$refresh');
    }

    protected function processTiptapBlock(array $block): string
    {
        $type = $block['type'] ?? '';
        
        switch ($type) {
            case 'paragraph':
                $content = $this->processTiptapInlineContent($block['content'] ?? []);
                return "<p>{$content}</p>";
                
            case 'heading':
                $level = $block['attrs']['level'] ?? 2;
                $content = $this->processTiptapInlineContent($block['content'] ?? []);
                return "<h{$level}>{$content}</h{$level}>";
                
            case 'bulletList':
                $items = array_map(fn($item) => $this->processTiptapBlock($item), $block['content'] ?? []);
                return '<ul>' . implode('', $items) . '</ul>';
                
            case 'orderedList':
                $items = array_map(fn($item) => $this->processTiptapBlock($item), $block['content'] ?? []);
                return '<ol>' . implode('', $items) . '</ol>';
                
            case 'listItem':
                $content = array_map(fn($item) => $this->processTiptapBlock($item), $block['content'] ?? []);
                return '<li>' . implode('', $content) . '</li>';
                
            case 'image':
                $src = $block['attrs']['src'] ?? '';
                $alt = $block['attrs']['alt'] ?? '';
                $title = $block['attrs']['title'] ?? '';
                return "<img src=\"{$src}\" alt=\"{$alt}\" title=\"{$title}\">";
                
            case 'codeBlock':
                $content = $this->processTiptapInlineContent($block['content'] ?? []);
                return "<pre><code>{$content}</code></pre>";
                
            case 'blockquote':
                $content = array_map(fn($item) => $this->processTiptapBlock($item), $block['content'] ?? []);
                return '<blockquote>' . implode('', $content) . '</blockquote>';
                
            case 'table':
                $rows = array_map(fn($item) => $this->processTiptapBlock($item), $block['content'] ?? []);
                return '<table>' . implode('', $rows) . '</table>';
                
            case 'tableRow':
                $cells = array_map(fn($item) => $this->processTiptapBlock($item), $block['content'] ?? []);
                return '<tr>' . implode('', $cells) . '</tr>';
                
            case 'tableHeader':
                $content = array_map(fn($item) => $this->processTiptapBlock($item), $block['content'] ?? []);
                return '<th>' . implode('', $content) . '</th>';
                
            case 'tableCell':
                $content = array_map(fn($item) => $this->processTiptapBlock($item), $block['content'] ?? []);
                return '<td>' . implode('', $content) . '</td>';
                
            default:
                return '';
        }
    }

    protected function processTiptapInlineContent(array $content): string
    {
        $html = '';
        
        foreach ($content as $item) {
            $type = $item['type'] ?? '';
            
            if ($type === 'text') {
                $text = htmlspecialchars($item['text'] ?? '', ENT_QUOTES, 'UTF-8');
                $marks = $item['marks'] ?? [];
                
                foreach ($marks as $mark) {
                    switch ($mark['type']) {
                        case 'bold':
                            $text = "<strong>{$text}</strong>";
                            break;
                        case 'italic':
                            $text = "<em>{$text}</em>";
                            break;
                        case 'underline':
                            $text = "<u>{$text}</u>";
                            break;
                        case 'strike':
                            $text = "<s>{$text}</s>";
                            break;
                        case 'code':
                            $text = "<code>{$text}</code>";
                            break;
                        case 'link':
                            $href = htmlspecialchars($mark['attrs']['href'] ?? '#', ENT_QUOTES, 'UTF-8');
                            $target = isset($mark['attrs']['target']) ? ' target="' . $mark['attrs']['target'] . '"' : '';
                            $text = "<a href=\"{$href}\"{$target}>{$text}</a>";
                            break;
                        case 'subscript':
                            $text = "<sub>{$text}</sub>";
                            break;
                        case 'superscript':
                            $text = "<sup>{$text}</sup>";
                            break;
                    }
                }
                
                $html .= $text;
            } elseif ($type === 'image') {
                $src = htmlspecialchars($item['attrs']['src'] ?? '', ENT_QUOTES, 'UTF-8');
                $alt = htmlspecialchars($item['attrs']['alt'] ?? '', ENT_QUOTES, 'UTF-8');
                $html .= "<img src=\"{$src}\" alt=\"{$alt}\" style=\"display: inline-block; vertical-align: middle;\">";
            } elseif ($type === 'hardBreak') {
                $html .= '<br>';
            }
        }
        
        return $html;
    }

    protected function getLastActivityDescription($record): string
    {
        $lastActivity = $record->activities()->latest()->first();
        
        if (!$lastActivity) {
            return 'No activity recorded';
        }
        
        $description = $lastActivity->description;
        $time = $lastActivity->created_at->format('M d, Y, H:i:s');
        
        return strtolower($description) . ' on ' . $time;
    }

    protected function getCustomFieldsDisplay(): array
    {
        $form = null;
        
        if ($this->record->form_id) {
            $form = Form::with('fields')->find($this->record->form_id);
        }
        
        if (!$form) {
            $form = $this->record->department?->forms()->with('fields')->first();
        }
        
        if (!$form || !$form->fields->count() || empty($this->record->custom_fields)) {
            return [];
        }

        $entries = [];

        foreach ($form->fields as $field) {
            $value = $this->record->custom_fields[$field->name] ?? null;
            
            if ($value !== null) {
                if ($field->type === 'file' || $field->type === 'file_multiple') {
                    $entries[] = Livewire::make(
                                    TicketAttachmentsDisplay::class,
                                    [
                                        'ticketId' => $this->record->id,
                                        'files' => $value,
                                        'label' => $field->label,
                                    ]
                            )->key(fn() => 'files-' . $this->record->id);
                } else {
                    $formattedValue = $this->formatCustomFieldValue($value, $field);
                    $isHtml = in_array($field->type, ['textarea', 'rich_editor']);
                    
                    $entries[] = TextEntry::make("custom_field_{$field->name}")
                        ->label($field->label)
                        ->state($formattedValue)
                        ->html($isHtml);
                }
            }
        }

        return $entries;
    }

    protected function formatCustomFieldValue($value, $field): string
    {
        if ($field->type === 'checkbox' || $field->type === 'toggle') {
            return $value ? __('creators-ticketing::resources.ticket.yes') : __('creators-ticketing::resources.ticket.no');
        }
        
        if ($field->type === 'select' || $field->type === 'radio') {
            $options = $field->options ?? [];
            return $options[$value] ?? $value;
        }
        
        if ($field->type === 'date' || $field->type === 'datetime') {
            try {
                $date = \Carbon\Carbon::parse($value);
                return $field->type === 'datetime' 
                    ? $date->format('M d, Y H:i:s') 
                    : $date->format('M d, Y');
            } catch (\Exception $e) {
                return $value;
            }
        }
        
        return is_string($value) ? $value : json_encode($value);
    }

    public function infolist(Schema $schema): Schema
    {
        $permissions = $this->getUserPermissions();
        $user = Filament::auth()->user();
        $recordDepartmentId = $this->record->department_id;

        $canReplyToTickets = $permissions['is_admin'] || 
                             ($this->record->user_id === $user->getKey() && config('creators-ticketing.allow_requester_to_reply', true)) ||
                             (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_reply_to_tickets']);
        
        $canAddInternalNotes = $permissions['is_admin'] || 
                               (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_add_internal_notes']);

        $canViewInternalNotes = $permissions['is_admin'] || 
                               (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_view_internal_notes']);

        return $schema
            ->schema([
                Tabs::make('Tabs')
                    ->id('ticket-view-tabs') 
                    ->tabs([
                        Tab::make(__('creators-ticketing::resources.ticket.ticket_view'))
                            ->icon('heroicon-o-ticket')
                            ->schema([
                                Group::make()
                                    ->schema([
                                        Section::make()
                                            ->schema([
                                                Group::make()
                                                    ->schema([
                                                        TextEntry::make('requester')
                                                            ->label(__('creators-ticketing::resources.ticket.requester'))
                                                            ->formatStateUsing(fn ($record) => UserNameResolver::resolve($record->requester))
                                                            ->icon('heroicon-o-user-circle')
                                                            ->size(TextSize::Large)
                                                            ->weight(FontWeight::Bold),
                                                    ])
                                                    ->columnStart(1),
                                                
                                                Group::make()
                                                    ->schema([
                                                        TextEntry::make('Status')
                                                            ->formatStateUsing(fn ($record) => 
                                                                $record->status?->name ? 
                                                                    "<span style='
                                                                        display: inline-flex;
                                                                        align-items: center;
                                                                        background-color: {$record->status->color}10;
                                                                        color: {$record->status->color};
                                                                        padding: 0.3rem 0.8rem;
                                                                        border-radius: 9999px;
                                                                        font-size: 0.7rem;
                                                                        font-weight: 600;
                                                                        line-height: 1;
                                                                        border: 1.5px solid {$record->status->color};
                                                                        white-space: nowrap;
                                                                    '>{$record->status->name}</span>" 
                                                                : ''
                                                            )
                                                            ->html(),
                                                        TextEntry::make('priority')
                                                            ->badge(),
                                                    ])
                                                    ->columns(2)
                                                    ->columnStart(3),
                                                
                                            ])
                                            ->columns(2)
                                            ->heading(false),
                                        
                                        Section::make(__('creators-ticketing::resources.ticket.form_data'))
                                            ->schema(fn() => $this->getCustomFieldsDisplay())
                                            ->columns(2)
                                            ->visible(fn() => count($this->getCustomFieldsDisplay()) > 0)
                                            ->collapsible(),
                                        
                                        Section::make()
                                            ->schema([
                                                Group::make()
                                                    ->schema([
                                                        Livewire::make(TicketChatMessages::class, ['ticket' => $this->record, 'canViewInternalNotes' => $canViewInternalNotes])
                                                            ->key(fn() => 'chat-' . $this->record->id),
                                                    ])
                                                    ->visible(fn($record) => $record->publicReplies()->exists() || $canViewInternalNotes),
                                                
                                                Group::make()
                                                    ->schema([
                                                        SchemaForm::make([
                                                            RichEditor::make('content')
                                                                ->label(__('creators-ticketing::resources.ticket.add_reply'))
                                                                ->required()
                                                                ->columnSpanFull()
                                                                ->placeholder(__('creators-ticketing::resources.ticket.reply_placeholder'))
                                                                ->toolbarButtons([
                                                                    ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
                                                                    ['h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                                                                    ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                                                                    ['table', 'attachFiles'],
                                                                    ['undo', 'redo'],
                                                                ])
                                                                ->extraInputAttributes(['style' => 'min-height: 8rem;'])
                                                                ->fileAttachmentsDirectory('ticket-attachments/' . $this->record->id)
                                                                ->fileAttachmentsDisk('private')
                                                                ->fileAttachmentsVisibility('private')
                                                                ->fileAttachmentsAcceptedFileTypes(['image/png', 'image/jpeg']),
                                                        ])
                                                        ->statePath('replyData')
                                                        ->livewireSubmitHandler('submitReply')
                                                        ->footer([
                                                            SchemaActions::make([
                                                                Toggle::make('is_internal_note')
                                                                    ->label(__('creators-ticketing::resources.ticket.internal_note'))
                                                                    ->helperText(__('creators-ticketing::resources.ticket.internal_note_helper'))
                                                                    ->inline(true)
                                                                    ->default(false)
                                                                    ->visible(fn() => $canAddInternalNotes),
                                                                Action::make('submitReply')
                                                                    ->submit('submitReply')
                                                                    ->label(__('creators-ticketing::resources.ticket.submit'))
                                                                    ->color('success')
                                                                    ->visible(fn() => $canReplyToTickets),
                                                            ]),
                                                        ]),
                                                    ])
                                                    ->extraAttributes(['class' => 'border-t border-gray-700 pt-6 mt-0'])
                                                    ->visible(fn() => $canReplyToTickets),
                                            ])
                                            ->heading(__('creators-ticketing::resources.ticket.conversation'))
                                            ->icon('heroicon-o-chat-bubble-left-right')
                                            ->visible(fn() => $canReplyToTickets || $canAddInternalNotes || $canViewInternalNotes)
                                            ->compact(false),
                                    ])
                                    ->columnSpan(['lg' => 2]),
                                
                                Group::make()
                                    ->schema([
                                        Section::make(__('creators-ticketing::resources.ticket.details'))
                                            ->icon('heroicon-o-document-text')
                                            ->schema([
                                                TextEntry::make('ticket_uid')
                                                    ->label(__('creators-ticketing::resources.ticket.ticket_no'))
                                                    ->copyable()
                                                    ->icon('heroicon-o-hashtag'),
                                                
                                                TextEntry::make('department.name')
                                                    ->label(__('creators-ticketing::resources.ticket.department'))
                                                    ->icon('heroicon-o-building-office-2')
                                                    ->color('success'),
                                                
                                                TextEntry::make('created_at')
                                                    ->label(__('creators-ticketing::resources.ticket.created_at'))
                                                    ->dateTime('M d, Y H:i:s')
                                                    ->icon('heroicon-o-clock')
                                                    ->color('success'),
                                                
                                                TextEntry::make('updated_at')
                                                    ->label(__('creators-ticketing::resources.ticket.updated_at'))
                                                    ->dateTime('M d, Y H:i:s')
                                                    ->icon('heroicon-o-clock')
                                                    ->color('success'),
                                                
                                                TextEntry::make('assignee')
                                                    ->label(__('creators-ticketing::resources.ticket.assignee'))
                                                    ->formatStateUsing(fn ($record) => $record->assignee ? UserNameResolver::resolve($record->assignee) : __('creators-ticketing::resources.ticket.unassigned'))
                                                    ->default(__('creators-ticketing::resources.ticket.unassigned'))
                                                    ->icon('heroicon-o-user-plus')
                                                    ->color('success'),
                                                
                                                TextEntry::make('last_activity_at')
                                                    ->label(__('creators-ticketing::resources.ticket.last_activity'))
                                                    ->formatStateUsing(fn($record) => $this->getLastActivityDescription($record))
                                                    ->icon('heroicon-o-clock')
                                                    ->color('danger')
                                                    ->visible(fn($record) => $record->last_activity_at !== null),
                                            ])
                                            ->compact()
                                            ->collapsible(),
                                        
                                        Section::make(__('creators-ticketing::resources.ticket.recent_activities'))
                                            ->icon('heroicon-o-clock')
                                            ->description(__('creators-ticketing::resources.ticket.latest_activities'))
                                            ->schema([
                                                 Livewire::make(TicketTimeline::class, [
                                                    'ticket' => $this->record,
                                                    'limit' => 5
                                                ])->key(fn() => 'timeline-preview-' . $this->record->id),
                                            ])
                                            ->collapsed()
                                            ->collapsible(),
                                    ])
                                    ->columnSpan(['lg' => 1]),
                            ])
                            ->columns(3),
                        
                        Tab::make(__('creators-ticketing::resources.ticket.full_timeline'))
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Section::make(__('creators-ticketing::resources.ticket.activity_timeline'))
                                    ->icon('heroicon-o-clock')
                                    ->description(__('creators-ticketing::resources.ticket.activities_history'))
                                    ->schema([
                                        Livewire::make(TicketTimeline::class, ['ticket' => $this->record])
                                            ->key(fn() => 'timeline-full-' . $this->record->id),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTab()
                    ->activeTab(1),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $permissions = $this->getUserPermissions();
        $recordDepartmentId = $this->record->department_id;
        
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);

        $canChangeStatus = $permissions['is_admin'] || 
                           (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_change_status']);
        
        $canChangePriority = $permissions['is_admin'] || 
                             (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_change_priority']);

        $canAssignTickets = $permissions['is_admin'] || 
                            (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_assign_tickets']);
        
        $canDeleteTickets = $permissions['is_admin'] || 
                            (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_delete_tickets']);
        
        $canChangeDepartment = $permissions['is_admin'] || 
                          (isset($permissions['permissions'][$recordDepartmentId]) && 
                           $permissions['permissions'][$recordDepartmentId]['can_change_departments']);

        $statusActions = [];
        if ($canChangeStatus) {
            foreach (TicketStatus::all() as $status) {
                $statusActions[] = Action::make('status_' . $status->id)
                    ->label($status->name)
                    ->action(fn() => $this->changeStatus($status->id));
            }
        }

        $priorityActions = [];
        if ($canChangePriority) {
            foreach (TicketPriority::cases() as $priority) {
                $priorityActions[] = Action::make('priority_' . $priority->value)
                    ->label($priority->getLabel())
                    ->action(fn() => $this->changePriority($priority->value));
            }
        }

        return [
            ActionGroup::make($statusActions)
                ->label(__('creators-ticketing::resources.ticket.actions.change_status'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->button()
                ->visible(fn() => $canChangeStatus),
            
            ActionGroup::make($priorityActions)
                ->label(__('creators-ticketing::resources.ticket.actions.change_priority'))
                ->icon('heroicon-o-flag')
                ->color('gray')
                ->button()
                ->visible(fn() => $canChangePriority),
            
            Action::make('assignTicket')
                ->label(__('creators-ticketing::resources.ticket.actions.assign'))
                ->icon('heroicon-o-user-plus')
                ->color('gray')
                ->button()
                ->visible(fn() => $canAssignTickets)
                ->form([
                   (config('creators-ticketing.ticket_assign_scope') === 'department_only') 
                           ? Select::make('assignee')
                                    ->label(__('creators-ticketing::resources.ticket.actions.select_assignee'))
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) {
                                        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
                                        $departmentId = $this->record->department_id;
                                        $userInstance = new $userModel;
                                        $userKey = $userInstance->getKeyName();

                                        return $userModel::when(
                                            config('creators-ticketing.ticket_assign_scope') === 'department_only' && $departmentId !== null,
                                            fn ($query) => $query->whereExists(function ($subquery) use ($departmentId, $userKey) {
                                                $subquery->select(DB::raw(1))
                                                    ->from(config('creators-ticketing.table_prefix') . 'department_users')
                                                    ->whereColumn(
                                                        config('creators-ticketing.table_prefix') . "department_users.user_id",
                                                        "users.{$userKey}"
                                                    )
                                                    ->where(config('creators-ticketing.table_prefix') . 'department_users.department_id', $departmentId);
                                            })
                                        )
                                        ->where(function ($query) use ($search) {
                                            $nameColumn = config('creators-ticketing.user_name_column', 'name');
                                            $query->where($nameColumn, 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                                        })
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn($user) => [$user->getKey() => UserNameResolver::resolve($user) . ' - ' . $user->email]);
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
                                        $user = $userModel::find($value);
                                        return $user ? UserNameResolver::resolve($user) . ' - ' . $user->email : null;
                                    })
                                    ->options(function () {
                                        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
                                        if (config('creators-ticketing.ticket_assign_scope') === 'department_only') {
                                            $departmentId = $this->record->department_id;
                                            if ($departmentId) {
                                                return Department::find($departmentId)?->agents->mapWithKeys(fn($user) => [$user->getKey() => UserNameResolver::resolve($user) . ' - ' . $user->email])->toArray() ?? [];
                                            }
                                        }
                                        return $userModel::limit(50)
                                            ->get()
                                            ->mapWithKeys(fn($user) => [$user->getKey() => UserNameResolver::resolve($user) . ' - ' . $user->email])
                                            ->toArray();
                                    })
                                    ->default($this->record->assignee_id)
                                    ->preload(fn() => config('creators-ticketing.ticket_assign_scope') === 'department_only' && $this->record->department_id !== null)
                                    ->placeholder(__('creators-ticketing::resources.ticket.unassigned'))
                                    ->native(false)
                        
                            :   Select::make('assignee')
                                    ->label(__('creators-ticketing::resources.ticket.actions.select_assignee'))
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) {
                                        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
                                        $nameColumn = config('creators-ticketing.user_name_column', 'name');
                                        return $userModel::where($nameColumn, 'like', "%{$search}%")
                                            ->orWhere('email', 'like', "%{$search}%")
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn($user) => [$user->getKey() => UserNameResolver::resolve($user) . ' - ' . $user->email]);
                                    })
                                    ->getOptionLabelUsing(function ($value) {
                                        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
                                        $user = $userModel::find($value);
                                        return $user ? UserNameResolver::resolve($user) . ' - ' . $user->email : null;
                                    })
                                    ->default($this->record->assignee_id)
                                    ->preload(false)
                                    ->placeholder(__('creators-ticketing::resources.ticket.unassigned'))
                                    ->native(false),
                ])
                ->action(function (array $data) {
                    $this->assignTicket($data['assignee']);
                }),

            Action::make('transferTicket')
                ->label(__('creators-ticketing::resources.ticket.actions.transfer'))
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('warning')
                ->button()
                ->visible(fn() => $canChangeDepartment)
                ->form([
                Select::make('department')
                    ->label(__('creators-ticketing::resources.ticket.actions.transfer_dept'))
                    ->options(function () use ($permissions) {
                        if ($permissions['is_admin']) {
                            return Department::where('is_active', true)
                                ->pluck('name', 'id');
                        }
                        
                        $allowedDepartmentIds = collect($permissions['permissions'])
                            ->filter(fn($perm) => $perm['can_change_departments'])
                            ->keys()
                            ->toArray();
                        
                        return Department::where('is_active', true)
                            ->whereIn('id', $allowedDepartmentIds)
                            ->pluck('name', 'id');
                    })
                    ->default(fn() => $this->record->department_id)
                    ->required()
                    ->searchable()
                    ->live()
                    ->helperText(__('creators-ticketing::resources.ticket.actions.transfer_dept_helper'))
                    ->native(false),
                
                    Select::make('assignee')
                        ->label(__('creators-ticketing::resources.ticket.actions.transfer_agent'))
                        ->placeholder(__('creators-ticketing::resources.ticket.actions.transfer_agent_placeholder'))
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search, Get $get) use ($userModel) {
                            $departmentId = $get('department');
                            
                            if (!$departmentId) {
                                return [];
                            }
                            
                            $userInstance = new $userModel;
                            $userKey = $userInstance->getKeyName();
                            $tablePrefix = config('creators-ticketing.table_prefix');
                            
                            return $userModel::whereExists(function ($subquery) use ($departmentId, $userKey, $tablePrefix, $userModel) {
                                $subquery->select(DB::raw(1))
                                    ->from($tablePrefix . 'department_users')
                                    ->whereColumn(
                                        $tablePrefix . "department_users.user_id",
                                        (new $userModel)->getTable() . ".{$userKey}"
                                    )
                                    ->where($tablePrefix . 'department_users.department_id', $departmentId);
                            })
                            ->where(function ($query) use ($search) {
                                $nameColumn = config('creators-ticketing.user_name_column', 'name');
                                $query->where($nameColumn, 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            })
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn($user) => [$user->getKey() => UserNameResolver::resolve($user) . ' - ' . $user->email]);
                        })
                        ->getOptionLabelUsing(function ($value) use ($userModel): ?string {
                            $user = $userModel::find($value);
                            return $user ? UserNameResolver::resolve($user) . ' - ' . $user->email : null;
                        })
                        ->options(function (Get $get) use ($userModel): array {
                            $departmentId = $get('department');
                            
                            if (!$departmentId) {
                                return [];
                            }
                            
                            $department = Department::find($departmentId);
                            return $department?->agents
                                ->mapWithKeys(fn($user) => [$user->getKey() => UserNameResolver::resolve($user) . ' - ' . $user->email])
                                ->toArray() ?? [];
                        })
                        ->default(function (Get $get) {
                            $departmentId = $get('department');
                            $currentAssigneeId = $this->record->assignee_id;
                            
                            if (!$currentAssigneeId || !$departmentId) {
                                return null;
                            }
                            
                            $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
                            $userInstance = new $userModel;
                            $userKey = $userInstance->getKeyName();
                            
                            $isInNewDept = DB::table(config('creators-ticketing.table_prefix') . 'department_users')
                                ->where('department_id', $departmentId)
                                ->where('user_id', $currentAssigneeId)
                                ->exists();
                            
                            return $isInNewDept ? $currentAssigneeId : null;
                        })
                        ->preload(fn(Get $get) => $get('department') !== null)
                        ->helperText(__('creators-ticketing::resources.ticket.actions.transfer_agent_helper'))
                        ->native(false),
                    ])
                    ->action(function (array $data) {
                        $this->transferTicket(
                            $data['department'], 
                            $data['assignee'] ?? null,
                            empty($data['assignee'])
                        );
                    })
                ->modalHeading(__('creators-ticketing::resources.ticket.actions.transfer_modal_heading'))
                ->modalDescription(__('creators-ticketing::resources.ticket.actions.transfer_modal_desc'))
                ->modalSubmitActionLabel(__('creators-ticketing::resources.ticket.actions.transfer_submit'))
                ->modalWidth('md'),
            
            Action::make('delete')
                ->label(__('creators-ticketing::resources.ticket.actions.delete'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn() => $canDeleteTickets)
                ->action(function () {
                    $this->record->delete();
                    return redirect()->to(TicketResource::getUrl('index'));
                }),
        ];
    }

    public function getRelationManagers(): array
    {
        $permissions = $this->getUserPermissions();
        $recordDepartmentId = $this->record->department_id;
        $canViewInternalNotes = $permissions['is_admin'] || 
                               (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_view_internal_notes']);

        if ($canViewInternalNotes) {
            return [
                InternalNotesRelationManager::class,
            ];
        }
        return [];
    }
}