<?php

namespace daacreators\CreatorsTicketing\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use daacreators\CreatorsTicketing\Models\Form;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\Department;
use daacreators\CreatorsTicketing\Models\TicketStatus;
use daacreators\CreatorsTicketing\Events\TicketCreated;
use daacreators\CreatorsTicketing\Support\TicketFileHelper;

class TicketSubmitForm extends Component
{
    use WithFileUploads;

    #[Url(as: 'tab', except: 'new')] 
    public $activeTab = 'new';

    #[Url(as: 'ticket', except: '')]
    public $urlTicketId = '';

    public $department_id;
    public $form_id;
    public $custom_fields = [];
    public $form_fields = [];
    public $departments = [];
    public $available_forms = []; 
    public $userTickets = [];
    public $showForm = true; 
    public $selectedTicket = null; 

    public function mount()
    {
        $this->departments = Department::public()->get();
        $this->loadUserTickets();

        if ($this->urlTicketId) {
            $this->viewTicket($this->urlTicketId);
        } elseif ($this->activeTab === 'list') {
            $this->showList();
        } else {
            $this->showNewTicketForm();
        }
    }

    protected function loadUserTickets()
    {
        if (auth()->check()) {
            $this->userTickets = Ticket::where('user_id', auth()->id())
                ->with(['department', 'status', 'publicReplies'])
                ->orderBy('last_activity_at', 'desc')
                ->get();
        }
    }

    public function showNewTicketForm()
    {
        $this->activeTab = 'new';
        $this->urlTicketId = '';
        $this->showForm = true;
        $this->selectedTicket = null;
    }

    public function showList()
    {
        $this->activeTab = 'list';
        $this->urlTicketId = '';
        $this->showForm = false;
        $this->selectedTicket = null;
    }

     public function viewTicket($ticketId)
    {
        $this->selectedTicket = Ticket::with(['department', 'status', 'publicReplies.user'])
            ->where('id', $ticketId)
            ->where('user_id', auth()->id())
            ->first();

        if ($this->selectedTicket) {
             $this->activeTab = 'view';
             $this->urlTicketId = $ticketId;
             $this->showForm = false;

             foreach ($this->selectedTicket->publicReplies as $r) {
                 $r->markSeenBy(auth()->id());
             }
             $this->selectedTicket->markSeenBy(auth()->id());
        } else {
            $this->backToList();
        }
    }
    
    public function backToList()
    {
        $this->loadUserTickets();
        $this->showList();
    }

    public function updatedDepartmentId()
    {
        $this->reset(['form_id', 'custom_fields', 'form_fields', 'available_forms']);

        if (!$this->department_id) return;

        $department = Department::find($this->department_id);
        if (!$department) return;

        $forms = $department->forms()->where('is_active', true)->get();

        if ($forms->count() === 1) {
            $this->form_id = $forms->first()->id;
            $this->loadFormFields();
        } elseif ($forms->count() > 1) {
            $this->available_forms = $forms;
        }
    }

    public function updatedFormId()
    {
        $this->custom_fields = [];
        $this->loadFormFields();
    }

    protected function loadFormFields()
    {
        if (!$this->form_id) {
            $this->form_fields = [];
            return;
        }
        $form = Form::with('fields')->find($this->form_id);
        $this->form_fields = $form && $form->fields->count() ? $form->fields->toArray() : [];
    }

    public function removeFile($fieldName, $index = null)
    {
        if (!isset($this->custom_fields[$fieldName])) return;

        if (is_array($this->custom_fields[$fieldName]) && $index !== null) {
            unset($this->custom_fields[$fieldName][$index]);
            $this->custom_fields[$fieldName] = array_values($this->custom_fields[$fieldName]);
        } else {
            $this->custom_fields[$fieldName] = null;
        }
    }

    public function getRules()
    {
        $rules = [
            'department_id' => 'required|exists:' . config('creators-ticketing.table_prefix') . 'departments,id',
        ];

        if (count($this->available_forms) > 1) {
            $rules['form_id'] = 'required|exists:' . config('creators-ticketing.table_prefix') . 'forms,id';
        }

        foreach ($this->form_fields as $field) {

            $key = "custom_fields.{$field['name']}";

            if ($field['type'] === 'file_multiple') {
                
                $parentRules = $field['is_required'] ? ['required', 'array'] : ['nullable', 'array'];
                
                $fileRules = ['file'];

                if (!empty($field['validation_rules'])) {
                    $rawRules = explode('|', $field['validation_rules']);
                    
                    foreach ($rawRules as $rule) {
                        $rule = trim($rule);
                        
                        if (str_starts_with($rule, 'max_files:')) {
                            $count = explode(':', $rule)[1] ?? 5;
                            $parentRules[] = "max:$count"; 
                        } 
                        elseif (str_starts_with($rule, 'min_files:')) {
                            $count = explode(':', $rule)[1] ?? 1;
                            $parentRules[] = "min:$count";
                        } 
                        else {
                            $fileRules[] = $rule;
                        }
                    }
                } else {
                    $fileRules[] = 'max:5120';
                }

                $rules[$key] = $parentRules;
                $rules["{$key}.*"] = array_unique($fileRules);

            } elseif ($field['type'] === 'file') {
                $baseRules = $this->getFieldValidationRules($field);
                $rules[$key] = array_merge(['file'], $baseRules);
            } else {
                $rules[$key] = $this->getFieldValidationRules($field);
            }
        }

        return $rules;
    }

    public function getFieldValidationRules(array $field): array
    {
        $rules = [];

        if ($field['type'] !== 'file' && $field['type'] !== 'file_multiple') {
            $rules[] = $field['is_required'] ? 'required' : 'nullable';
        } elseif ($field['is_required'] && $field['type'] === 'file') {
            $rules[] = 'required';
        }

        if (!empty($field['validation_rules'])) {
            $rawRules = explode('|', $field['validation_rules']);
            
            foreach ($rawRules as $rule) {
                $rule = trim($rule);
                

                if (str_starts_with($rule, 'max_files:') || str_starts_with($rule, 'min_files:')) {
                    continue;
                }
                
                $rules[] = $rule;
            }
        } else {
            switch ($field['type']) {
                case 'email': $rules[] = 'email'; break;
                case 'number': $rules[] = 'numeric'; break;
                case 'url': $rules[] = 'url'; break;
                case 'file':
                case 'file_multiple':
                    $rules[] = 'max:5120';
                    break;
            }
        }

        return array_values(array_unique($rules));
    }

    public function validationAttributes()
    {
        $attributes = [
            'department_id' => __('creators-ticketing::resources.frontend.select_department'),
            'form_id' => __('creators-ticketing::resources.frontend.category_label'),
        ];

        foreach ($this->form_fields as $field) {
            $attributes["custom_fields.{$field['name']}"] = $field['label'];
            $attributes["custom_fields.{$field['name']}.*"] = $field['label'] . ' (File)';
        }

        return $attributes;
    }

    public function submit()
    {
        $maxTickets = config('creators-ticketing.max_open_tickets_per_user');
            
        if ($maxTickets && $maxTickets > 0) {
            $openTicketsCount = Ticket::where('user_id', auth()->id())
                ->whereHas('status', fn($q) => $q->where('is_closing_status', false))
                ->count();
            
            if ($openTicketsCount >= $maxTickets) {
                session()->flash('error', config('creators-ticketing.ticket_limit_message'));
                return;
            }
        }

        $this->validate();

        $defaultStatus = TicketStatus::where('is_default_for_new', true)->first();
        
        $tempCustomFields = $this->custom_fields;
        
        foreach ($this->form_fields as $field) {
            if (in_array($field['type'], ['file', 'file_multiple'])) {
                unset($tempCustomFields[$field['name']]);
            }
        }

        $ticket = Ticket::create([
            'department_id' => $this->department_id,
            'form_id' => $this->form_id,
            'custom_fields' => $tempCustomFields,
            'user_id' => auth()->id(),
            'ticket_status_id' => $defaultStatus?->id,
            'last_activity_at' => now(),
        ]);

        $finalCustomFields = $ticket->custom_fields ?? [];
        $hasFilesToUpload = false;

        foreach ($this->form_fields as $field) {
            if (in_array($field['type'], ['file', 'file_multiple']) && !empty($this->custom_fields[$field['name']])) {
                
                $files = $this->custom_fields[$field['name']];
                
                if (!is_array($files)) {
                    $files = [$files];
                }

                $storedPaths = TicketFileHelper::processUploadedFiles($files, $ticket->id);
                
                $finalCustomFields[$field['name']] = $storedPaths;
                $hasFilesToUpload = true;
            }
        }

        if ($hasFilesToUpload) {
            $ticket->update(['custom_fields' => $finalCustomFields]);
        }

        event(new TicketCreated($ticket, auth()->user()));

        session()->flash('success', 'Ticket submitted successfully!');

        $this->reset(['department_id', 'form_id', 'custom_fields', 'form_fields', 'available_forms']);
        $this->showList(); 
        $this->loadUserTickets();
    }

    public function render()
    {
        return view('creators-ticketing::livewire.ticket-submit-form');
    }
}