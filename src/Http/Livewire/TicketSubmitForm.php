<?php

namespace daacreators\CreatorsTicketing\Http\Livewire;

use daacreators\CreatorsTicketing\Models\Department;
use daacreators\CreatorsTicketing\Models\Form;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\TicketStatus;
use Livewire\Component;
use Livewire\WithFileUploads;

class TicketSubmitForm extends Component
{
    use WithFileUploads;

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

    public function viewTicket($ticketId)
    {
        $this->selectedTicket = Ticket::with(['department', 'status', 'publicReplies.user'])
            ->where('id', $ticketId)
            ->where('user_id', auth()->id())
            ->first();

        if ($this->selectedTicket) {
            foreach ($this->selectedTicket->publicReplies as $r) {
                $r->markSeenBy(auth()->id());
            }

            $this->selectedTicket->markSeenBy(auth()->id());
        }
        $this->showForm = false;
    }

    public function backToList()
    {
        $this->selectedTicket = null;
        $this->showForm = false;
        $this->loadUserTickets();
    }

    public function updatedDepartmentId()
    {
        $this->reset(['form_id', 'custom_fields', 'form_fields', 'available_forms']);

        if (! $this->department_id) {
            return;
        }

        $department = Department::find($this->department_id);
        if (! $department) {
            return;
        }

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
        if (! $this->form_id) {
            $this->form_fields = [];

            return;
        }
        $form = Form::with('fields')->find($this->form_id);
        $this->form_fields = $form && $form->fields->count() ? $form->fields->toArray() : [];
    }

    public function submit()
    {
        $maxTickets = config('creators-ticketing.max_open_tickets_per_user');

        if ($maxTickets && $maxTickets > 0) {
            $openTicketsCount = Ticket::where('user_id', auth()->id())
                ->whereHas('status', fn ($q) => $q->where('is_closing_status', false))
                ->count();

            if ($openTicketsCount >= $maxTickets) {
                session()->flash('error', config('creators-ticketing.ticket_limit_message'));

                return;
            }
        }

        $departmentTable = config('creators-ticketing.table_prefix').'departments';
        $formTable = config('creators-ticketing.table_prefix').'forms';

        $this->validate([
            'department_id' => "required|exists:{$departmentTable},id",
            'form_id' => "required|exists:{$formTable},id",
        ]);

        foreach ($this->form_fields as $field) {
            if ($field['is_required']) {
                $this->validate(
                    ["custom_fields.{$field['name']}" => 'required'],
                    ["custom_fields.{$field['name']}.required" => "The {$field['label']} field is required."]
                );
            }
        }

        $defaultStatus = TicketStatus::where('is_default_for_new', true)->first();

        Ticket::create([
            'department_id' => $this->department_id,
            'form_id' => $this->form_id,
            'custom_fields' => $this->custom_fields,
            'user_id' => auth()->id(),
            'ticket_status_id' => $defaultStatus?->id,
            'last_activity_at' => now(),
        ]);

        session()->flash('success', 'Ticket submitted successfully!');

        $this->reset(['department_id', 'form_id', 'custom_fields', 'form_fields', 'available_forms']);
        $this->showForm = false;
        $this->loadUserTickets();
    }

    public function render()
    {
        return view('creators-ticketing::livewire.ticket-submit-form');
    }
}
