<?php

namespace daacreators\CreatorsTicketing\Http\Livewire;

use daacreators\CreatorsTicketing\Models\Department;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\TicketStatus;
use Livewire\Component;
use Livewire\WithFileUploads;

class TicketSubmitForm extends Component
{
    use WithFileUploads;

    public $department_id;
    public $custom_fields = [];
    public $form_fields = [];
    public $departments = [];
    public $userTickets = [];
    public $showForm = true;
    public $selectedTicket = null;

    public function mount()
    {
        $this->departments = Department::public()->get();
        $this->loadUserTickets();
    }

    protected function loadUserTickets()
    {
        if (auth()->check()) {
            $this->userTickets = Ticket::where('user_id', auth()->id())
                ->with(['department', 'status', 'publicReplies'])
                ->orderBy('created_at', 'desc')
                ->get();
            foreach ($this->userTickets as $t) {
                if (!$t->is_seen) {
                    $t->markSeenBy(auth()->id());
                }
                foreach ($t->publicReplies as $r) {
                    if (!$r->is_seen) {
                        $r->markSeenBy(auth()->id());
                    }
                }
            }
        }
    }

    public function viewTicket($ticketId)
    {
        $this->selectedTicket = Ticket::with(['department', 'status', 'publicReplies.user'])
            ->where('id', $ticketId)
            ->where('user_id', auth()->id())
            ->first();
        $this->selectedTicket->markSeenBy(auth()->id());
        foreach ($this->selectedTicket->publicReplies as $r) {
            if (!$r->is_seen) {
                $r->markSeenBy(auth()->id());
            }
        }

        $this->showForm = false;
    }

    public function backToList()
    {
        $this->selectedTicket = null;
        $this->showForm = true;
        $this->loadUserTickets();
    }

    public function updatedDepartmentId()
    {
        $this->custom_fields = [];
        $this->loadFormFields();
    }

    protected function loadFormFields()
    {
        if (!$this->department_id) {
            $this->form_fields = [];
            return;
        }

        $department = Department::with('forms.fields')->find($this->department_id);
        
        if (!$department) {
            $this->form_fields = [];
            return;
        }

        $form = $department->forms()->with('fields')->first();
        
        $this->form_fields = $form && $form->fields->count() 
            ? $form->fields->toArray() 
            : [];
    }

    public function submit()
    {
        $maxTickets = config('creators-ticketing.max_open_tickets_per_user');
            
        if ($maxTickets && $maxTickets > 0) {
            $openTicketsCount = Ticket::where('user_id', auth()->id())
                ->whereHas('status', function($query) {
                    $query->where('is_closing_status', false);
                })
                ->count();
            
            if ($openTicketsCount >= $maxTickets) {
                session()->flash('error', config('creators-ticketing.ticket_limit_message'));
                return;
            }
        }

        $this->validate([
            'department_id' => 'required|exists:departments,id',
        ]);

        foreach ($this->form_fields as $field) {
            if ($field['is_required']) {
                $this->validate([
                    "custom_fields.{$field['name']}" => 'required',
                ], [
                    "custom_fields.{$field['name']}.required" => "The {$field['label']} field is required.",
                ]);
            }
        }

        $defaultStatus = TicketStatus::where('is_default', true)->first();

        Ticket::create([
            'department_id' => $this->department_id,
            'custom_fields' => $this->custom_fields,
            'user_id' => auth()->id(),
            'ticket_status_id' => $defaultStatus?->id,
            'last_activity_at' => now(),
        ]);

        session()->flash('success', 'Ticket submitted successfully! Our support team will respond soon.');

        $this->reset(['department_id', 'custom_fields', 'form_fields']);
        $this->loadUserTickets();
    }

    public function render()
    {
        return view('creators-ticketing::livewire.ticket-submit-form');
    }
}