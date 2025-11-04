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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Form as SchemaForm;
use daacreators\CreatorsTicketing\Models\Department;
use daacreators\CreatorsTicketing\Models\TicketStatus;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use Filament\Schemas\Components\Actions as SchemaActions;
use daacreators\CreatorsTicketing\Traits\HasTicketPermissions;
use daacreators\CreatorsTicketing\Http\Livewire\TicketTimeline;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use daacreators\CreatorsTicketing\Http\Livewire\TicketChatMessages;
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
            Notification::make()->title('Unauthorized')->danger()->body('You do not have permission to change ticket status.')->send();
            return;
        }

        $this->record->update(['ticket_status_id' => $statusId]);
        
        $this->record->activities()->create([
            'user_id' => Filament::auth()->id(),
            'description' => 'Status changed',
            'old_value' => $this->record->status?->name,
            'new_value' => TicketStatus::find($statusId)?->name,
        ]);
        
        Notification::make()->title('Status updated')->success()->send();
        
        $this->dispatch('$refresh');
    }

    public function changePriority($priority): void
    {
        $permissions = $this->getUserPermissions();
        $recordDepartmentId = $this->record->department_id;
        $canChangePriority = $permissions['is_admin'] || 
                             (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_change_priority']);

        if (!$canChangePriority) {
            Notification::make()->title('Unauthorized')->danger()->body('You do not have permission to change ticket priority.')->send();
            return;
        }

        $oldPriority = $this->record->priority;
        $this->record->update(['priority' => $priority]);
        
        $this->record->activities()->create([
            'user_id' => Filament::auth()->id(),
            'description' => 'Priority changed',
            'old_value' => $oldPriority?->getLabel(),
            'new_value' => TicketPriority::from($priority)->getLabel(),
        ]);
        
        Notification::make()->title('Priority updated')->success()->send();
        
        $this->dispatch('$refresh');
    }

    public function assignTicket($assigneeId): void
    {
        $permissions = $this->getUserPermissions();
        $recordDepartmentId = $this->record->department_id;
        $canAssignTickets = $permissions['is_admin'] || 
                            (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_assign_tickets']);

        if (!$canAssignTickets) {
            Notification::make()->title('Unauthorized')->danger()->body('You do not have permission to assign tickets.')->send();
            return;
        }

        $this->record->update(['assignee_id' => $assigneeId]);
        
        $this->record->activities()->create([
            'user_id' => Filament::auth()->id(),
            'description' => 'Ticket assigned',
            'new_value' => User::find($assigneeId)?->name,
        ]);
        
        Notification::make()->title('Ticket assigned')->success()->send();
        
        $this->dispatch('$refresh');
    }

    public function submitReply(): void
    {
        $permissions = $this->getUserPermissions();
        $user = Filament::auth()->user();
        $recordDepartmentId = $this->record->department_id;

        $canReplyToTickets = $permissions['is_admin'] || 
                             ($this->record->user_id === $user->id && config('creators-ticketing.allow_requester_to_reply', true)) ||
                             (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_reply_to_tickets']);
        
        $canAddInternalNotes = $permissions['is_admin'] || 
                               (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_add_internal_notes']);

        if (!$canReplyToTickets) {
            Notification::make()->title('Unauthorized')->danger()->body('You do not have permission to reply to tickets.')->send();
            return;
        }
        if (($this->replyData['is_internal_note'] ?? false) && !$canAddInternalNotes) {
             Notification::make()->title('Unauthorized')->danger()->body('You do not have permission to add internal notes.')->send();
            return;
        }

        $data = $this->replyData ?? [];
        
        if (empty(($data['content'] ?? ''))) {
            Notification::make()
                ->title('Reply cannot be empty')
                ->danger()
                ->send();
            return;
        }

        $content = $data['content'];
        $htmlContent = is_array($content) ? $this->convertTiptapToHtml($content) : $content;
        $htmlContent = $this->moveTempFilesToPermanentStorage($htmlContent);

        $this->record->replies()->create([
            'content' => str($htmlContent)->sanitizeHtml(),
            'user_id' => Filament::auth()->id(),
            'is_internal_note' => $data['is_internal_note'] ?? false,
        ]);

        $this->record->activities()->create([
            'user_id' => Filament::auth()->id(),
            'description' => $data['is_internal_note'] ?? false ? 'Internal note added' : 'Reply sent',
            'new_value' => substr(strip_tags($htmlContent), 0, 100) . '...',
        ]);

        $this->record->touch('last_activity_at');

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
                ->title('Unauthorized')
                ->danger()
                ->body('You do not have permission to transfer tickets.')
                ->send();
            return;
        }

        $oldDepartment = $this->record->department;
        $newDepartment = Department::find($newDepartmentId);
        $currentAssigneeId = $this->record->assignee_id;

        $updateData = ['department_id' => $newDepartmentId];
        
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
            $newAssignee = User::find($updateData['assignee_id']);
            $activityDescription .= ' and assigned to ' . $newAssignee->name;
            $activityNewValue .= ' (Assigned: ' . $newAssignee->name . ')';
        } elseif ($currentAssigneeId && !isset($updateData['assignee_id'])) {
            $activityDescription .= ' (assignee removed - not in new department)';
        }

        $this->record->activities()->create([
            'user_id' => Filament::auth()->id(),
            'description' => 'Ticket transferred',
            'old_value' => $oldDepartment->name,
            'new_value' => $activityNewValue,
        ]);

        Notification::make()
            ->title('Ticket transferred successfully')
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
        $form = $this->record->department?->forms()->with('fields')->first();
        
        if (!$form || !$form->fields->count() || empty($this->record->custom_fields)) {
            return [];
        }

        $entries = [];

        foreach ($form->fields as $field) {
            $value = $this->record->custom_fields[$field->name] ?? null;
            
            if ($value !== null) {
                $formattedValue = $this->formatCustomFieldValue($value, $field);
                
                $entries[] = TextEntry::make("custom_field_{$field->name}")
                    ->label($field->label)
                    ->default($formattedValue)
                    ->html($field->type === 'textarea');
            }
        }

        return $entries;
    }

    protected function formatCustomFieldValue($value, $field): string
    {
        if ($field->type === 'checkbox' || $field->type === 'toggle') {
            return $value ? 'Yes' : 'No';
        }
        
        if ($field->type === 'select' || $field->type === 'radio') {
            $options = $field->options ?? [];
            return $options[$value] ?? $value;
        }
        
        if ($field->type === 'file') {
            if (is_array($value)) {
                return implode(', ', array_map(fn($file) => basename($file), $value));
            }
            return basename($value);
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
                             ($this->record->user_id === $user->id && config('creators-ticketing.allow_requester_to_reply', true)) ||
                             (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_reply_to_tickets']);
        
        $canAddInternalNotes = $permissions['is_admin'] || 
                               (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_add_internal_notes']);

        $canViewInternalNotes = $permissions['is_admin'] || 
                               (isset($permissions['permissions'][$recordDepartmentId]) && $permissions['permissions'][$recordDepartmentId]['can_view_internal_notes']);

        return $schema
            ->schema([
                Tabs::make('Tabs')
                    ->tabs([
                        Tab::make('Ticket View')
                            ->icon('heroicon-o-ticket')
                            ->schema([
                                Group::make()
                                    ->schema([
                                        Section::make()
                                            ->schema([
                                                Group::make()
                                                    ->schema([
                                                        TextEntry::make('requester.name')
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
                                        
                                        Section::make('Form Data')
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
                                                                ->label('Add Reply')
                                                                ->required()
                                                                ->columnSpanFull()
                                                                ->placeholder('Write your reply here...')
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
                                                                    ->label('Internal Note')
                                                                    ->helperText('This reply will only be visible to agents.')
                                                                    ->inline(true)
                                                                    ->default(false)
                                                                    ->visible(fn() => $canAddInternalNotes),
                                                                Action::make('submitReply')
                                                                    ->submit('submitReply')
                                                                    ->label('Submit')
                                                                    ->color('success')
                                                                    ->visible(fn() => $canReplyToTickets),
                                                            ]),
                                                        ]),
                                                    ])
                                                    ->extraAttributes(['class' => 'border-t border-gray-700 pt-6 mt-0'])
                                                    ->visible(fn() => $canReplyToTickets),
                                            ])
                                            ->heading('Conversation')
                                            ->icon('heroicon-o-chat-bubble-left-right')
                                            ->visible(fn() => $canReplyToTickets || $canAddInternalNotes || $canViewInternalNotes)
                                            ->compact(false),
                                    ])
                                    ->columnSpan(['lg' => 2]),
                                
                                Group::make()
                                    ->schema([
                                        Section::make('Ticket Details')
                                            ->icon('heroicon-o-document-text')
                                            ->schema([
                                                TextEntry::make('ticket_uid')
                                                    ->label('Ticket No')
                                                    ->copyable()
                                                    ->icon('heroicon-o-hashtag'),
                                                
                                                TextEntry::make('department.name')
                                                    ->label('Department')
                                                    ->icon('heroicon-o-building-office-2')
                                                    ->color('success'),
                                                
                                                TextEntry::make('created_at')
                                                    ->label('Created at')
                                                    ->dateTime('M d, Y H:i:s')
                                                    ->icon('heroicon-o-clock')
                                                    ->color('success'),
                                                
                                                TextEntry::make('updated_at')
                                                    ->label('Updated at')
                                                    ->dateTime('M d, Y H:i:s')
                                                    ->icon('heroicon-o-clock')
                                                    ->color('success'),
                                                
                                                TextEntry::make('assignee.name')
                                                    ->label('Assigned to')
                                                    ->default('Unassigned')
                                                    ->icon('heroicon-o-user-plus')
                                                    ->color('success'),
                                                
                                                TextEntry::make('last_activity_at')
                                                    ->label('Last Activity')
                                                    ->formatStateUsing(fn($record) => $this->getLastActivityDescription($record))
                                                    ->icon('heroicon-o-clock')
                                                    ->color('danger')
                                                    ->visible(fn($record) => $record->last_activity_at !== null),
                                            ])
                                            ->compact()
                                            ->collapsible(),
                                        
                                        Section::make('Recent Activities')
                                            ->icon('heroicon-o-clock')
                                            ->description('Last 5 activities')
                                            ->schema([
                                                Livewire::make(TicketTimeline::class, ['ticket' => $this->record])
                                                    ->key(fn() => 'timeline-preview-' . $this->record->id),
                                            ])
                                            ->collapsed()
                                            ->collapsible(),
                                    ])
                                    ->columnSpan(['lg' => 1]),
                            ])
                            ->columns(3),
                        
                        Tab::make('Full Timeline')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Section::make('Activity Timeline')
                                    ->icon('heroicon-o-clock')
                                    ->description('Complete activity history')
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
                ->label('Change Status')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->button()
                ->visible(fn() => $canChangeStatus),
            
            ActionGroup::make($priorityActions)
                ->label('Change Priority')
                ->icon('heroicon-o-flag')
                ->color('gray')
                ->button()
                ->visible(fn() => $canChangePriority),
            
            Action::make('assignTicket')
                ->label('Assign')
                ->icon('heroicon-o-user-plus')
                ->color('gray')
                ->button()
                ->visible(fn() => $canAssignTickets)
                ->form([
                   (config('creators-ticketing.ticket_assign_scope') === 'department_only') 
                           ? Select::make('assignee')
                                    ->label('Select Assignee')
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search, Component $component) use ($userModel) {
                                        $departmentId = $component->getLivewire()->record->department_id;

                                        return $userModel::when(
                                            config('creators-ticketing.ticket_assign_scope') === 'department_only' && $departmentId !== null,
                                            fn ($query) => $query->whereExists(function ($subquery) use ($departmentId) {
                                                $userInstance = new $userModel;
                                                $userKey = $userInstance->getKeyName();
                                                $pivotUserColumn = "user_{$userKey}";
                                                $subquery->select(DB::raw(1))
                                                    ->from(config('creators-ticketing.table_prefix') . 'department_users')
                                                    ->whereColumn(
                                                        config('creators-ticketing.table_prefix') . "department_users.{$pivotUserColumn}",
                                                        "users.{$userKey}"
                                                    )
                                                    ->where(config('creators-ticketing.table_prefix') . 'department_users.department_id', $departmentId);

                                            })
                                        )
                                        ->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn($user) => [$user->id => $user->name . ' - ' . $user->email]);
                                    })
                                    ->getOptionLabelUsing(fn ($value): ?string => 
                                        $userModel::find($value)?->name . ' - ' . $userModel::find($value)?->email
                                    )
                                    ->options(function (Component $component) use ($userModel): array {
                                        if (config('creators-ticketing.ticket_assign_scope') === 'department_only') {
                                            $departmentId = $component->getLivewire()->record->department_id;
                                            if ($departmentId) {
                                                return Department::find($departmentId)?->agents->mapWithKeys(fn($user) => [$user->id => $user->name . ' - ' . $user->email])->toArray() ?? [];
                                            }
                                        }
                                        return $userModel::limit(50)
                                            ->get()
                                            ->mapWithKeys(fn($user) => [$user->id => $user->name . ' - ' . $user->email])
                                            ->toArray();
                                    })
                                    ->default($this->record->assignee_id)
                                    ->preload(fn(Component $component) => config('creators-ticketing.ticket_assign_scope') === 'department_only' && $component->getLivewire()->record->department_id !== null)
                                    ->required()
                                    ->native(false)
                        
                            :   Select::make('assignee')
                                    ->label('Select Assignee')
                                    ->searchable()
                                    ->getSearchResultsUsing(function (string $search) use ($userModel) {
                                        return $userModel::where('name', 'like', "%{$search}%")
                                            ->orWhere('email', 'like', "%{$search}%")
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn($user) => [$user->id => $user->name . ' - ' . $user->email]);
                                    })
                                    ->getOptionLabelUsing(fn ($value): ?string => 
                                        $userModel::find($value)?->name . ' - ' . $userModel::find($value)?->email
                                    )
                                    ->default($this->record->assignee_id)
                                    ->preload(false)
                                    ->required()
                                    ->native(false),
                ])
                ->action(function (array $data) {
                    $this->assignTicket($data['assignee']);
                }),

            Action::make('transferTicket')
                ->label('Transfer')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('warning')
                ->button()
                ->visible(fn() => $canChangeDepartment)
                ->form([
                Select::make('department')
                    ->label('Transfer to Department')
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
                    ->helperText('Select the department to transfer this ticket to.')
                    ->native(false),
                
                    Select::make('assignee')
                        ->label('Assign to Agent')
                        ->placeholder('Keep current assignee if available, otherwise unassigned')
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search, Get $get) use ($userModel) {
                            $departmentId = $get('department');
                            
                            if (!$departmentId) {
                                return [];
                            }
                            
                            return $userModel::whereHas(config('creators-ticketing.table_prefix') . 'departments', function ($query) use ($departmentId) {
                                $query->where('department_id', $departmentId);
                            })
                            ->where(fn ($query) => $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                            )
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn($user) => [$user->id => $user->name . ' - ' . $user->email]);
                        })
                        ->getOptionLabelUsing(fn ($value): ?string => 
                            $userModel::find($value)?->name . ' - ' . $userModel::find($value)?->email
                        )
                        ->options(function (Get $get) use ($userModel): array {
                            $departmentId = $get('department');
                            
                            if (!$departmentId) {
                                return [];
                            }
                            
                            $department = Department::find($departmentId);
                            return $department?->agents
                                ->mapWithKeys(fn($user) => [$user->id => $user->name . ' - ' . $user->email])
                                ->toArray() ?? [];
                        })
                        ->default(function (Get $get) {
                            $departmentId = $get('department');
                            $currentAssigneeId = $this->record->assignee_id;
                            
                            if (!$currentAssigneeId || !$departmentId) {
                                return null;
                            }
                            
                            $isInNewDept = DB::table(config('creators-ticketing.table_prefix') . 'department_users')
                                ->where('department_id', $departmentId)
                                ->where('user_id', $currentAssigneeId)
                                ->exists();
                            
                            return $isInNewDept ? $currentAssigneeId : null;
                        })
                        ->preload(fn(Get $get) => $get('department') !== null)
                        ->helperText('Leave empty to keep current assignee (if they are in the new department) or leave unassigned.')
                        ->native(false),
                    ])
                    ->action(function (array $data) {
                        $this->transferTicket(
                            $data['department'], 
                            $data['assignee'] ?? null,
                            empty($data['assignee'])
                        );
                    })
                ->modalHeading('Transfer Ticket')
                ->modalDescription('Transfer this ticket to another department. Current assignee will be kept if they exist in the new department.')
                ->modalSubmitActionLabel('Transfer')
                ->modalWidth('md'),
            
            Action::make('delete')
                ->label('Delete')
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