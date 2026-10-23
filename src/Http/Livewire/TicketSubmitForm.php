<?php

namespace daacreators\CreatorsTicketing\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\Locked;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\WithFileUploads;
use daacreators\CreatorsTicketing\Models\Form;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\Department;
use daacreators\CreatorsTicketing\Models\TicketStatus;
use daacreators\CreatorsTicketing\Support\TicketFileHelper;
use daacreators\CreatorsTicketing\Services\SpamFilterService;

class TicketSubmitForm extends Component
{
    use WithFileUploads;

    #[Url(as: 'tab', except: 'new')]
    public $activeTab = 'new';

    #[Url(as: 'ticket', except: '')]
    public $urlTicketId = '';

    // Guest mode fields
    public $guest_name  = '';
    public $guest_email = '';

    // Guest tracking — set after successful submission
    #[Locked]
    public $guestTrackingUrl  = null;
    #[Locked]
    public $guestTrackingToken = null;

    // Form state
    public $department_id;
    public $form_id;
    public $custom_fields   = [];
    #[Locked]
    public $form_fields     = [];
    #[Locked]
    public $departments     = [];
    #[Locked]
    public $available_forms = [];
    #[Locked]
    public $userTickets     = [];
    public $showForm        = true;
    #[Locked]
    public $selectedTicket  = null;

    // ──────────────────────────────────────────────────────────────────────────

    public function mount()
    {
        // Preserve a host application's login flow when guests are disabled.
        if (!config('creators-ticketing.allow_guest_tickets') && !auth()->check()) {
            if (\Illuminate\Support\Facades\Route::has('login')) {
                $this->redirect(route('login'));
                return;
            }
            abort(403);
        }

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

    // ── Ticket list ───────────────────────────────────────────────────────────

    protected function loadUserTickets(): void
    {
        if (auth()->check()) {
            $this->userTickets = Ticket::where('user_id', auth()->id())
                ->with(['department', 'status', 'publicReplies'])
                ->orderBy('last_activity_at', 'desc')
                ->get();
        } else {
            $this->userTickets = collect();
        }
    }

    public function showNewTicketForm(): void
    {
        $this->activeTab      = 'new';
        $this->urlTicketId    = '';
        $this->showForm       = true;
        $this->selectedTicket = null;
        $this->guestTrackingUrl   = null;
        $this->guestTrackingToken = null;
    }

    public function showList(): void
    {
        $this->activeTab      = 'list';
        $this->urlTicketId    = '';
        $this->showForm       = false;
        $this->selectedTicket = null;
    }

    public function viewTicket($ticketId): void
    {
        $query = Ticket::with(['department', 'status', 'publicReplies.user'])
            ->where('id', $ticketId);

        if (auth()->check()) {
            $query->where('user_id', auth()->id());
        } else {
            // Guests can't browse by ID through this method
            $this->backToList();
            return;
        }

        $this->selectedTicket = $query->first();

        if ($this->selectedTicket) {
            $this->activeTab   = 'view';
            $this->urlTicketId = $ticketId;
            $this->showForm    = false;

            foreach ($this->selectedTicket->publicReplies as $r) {
                $r->markSeenBy(auth()->id());
            }
            $this->selectedTicket->markSeenBy(auth()->id());
        } else {
            $this->backToList();
        }
    }

    public function backToList(): void
    {
        $this->loadUserTickets();
        $this->showList();
    }

    // ── Department / form watchers ────────────────────────────────────────────

    public function updatedDepartmentId(): void
    {
        $this->reset(['form_id', 'custom_fields', 'form_fields', 'available_forms']);

        if (!$this->department_id) {
            return;
        }

        $department = Department::public()->find($this->department_id);
        if (!$department) {
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

    public function updatedFormId(): void
    {
        $this->custom_fields = [];
        $this->loadFormFields();
    }

    protected function loadFormFields(): void
    {
        if (!$this->form_id) {
            $this->form_fields = [];
            return;
        }
        $department = Department::public()->find($this->department_id);
        $form = $department?->forms()->where('is_active', true)->with('fields')->find($this->form_id);
        $this->form_fields = $form && $form->fields->count() ? $form->fields->toArray() : [];
    }

    public function removeFile($fieldName, $index = null): void
    {
        if (!isset($this->custom_fields[$fieldName])) {
            return;
        }

        if (is_array($this->custom_fields[$fieldName]) && $index !== null) {
            unset($this->custom_fields[$fieldName][$index]);
            $this->custom_fields[$fieldName] = array_values($this->custom_fields[$fieldName]);
        } else {
            $this->custom_fields[$fieldName] = null;
        }
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function getRules(): array
    {
        $rules = [
            'department_id' => ['required', Rule::exists(config('creators-ticketing.table_prefix') . 'departments', 'id')
                ->where('visibility', 'public')->where('is_active', true)],
        ];

        // Guest-only fields
        if ($this->isGuestMode()) {
            $rules['guest_name']  = 'required|string|max:100';
            $rules['guest_email'] = 'required|email|max:150';
        }

        $formIds = Department::public()->find($this->department_id)?->forms()
            ->where('is_active', true)->pluck(config('creators-ticketing.table_prefix') . 'forms.id')->all() ?? [];
        $rules['form_id'] = [count($formIds) > 0 ? 'required' : 'nullable', Rule::in($formIds)];

        foreach ($this->form_fields as $field) {
            $key = "custom_fields.{$field['name']}";

            if ($field['type'] === 'file_multiple') {
                $parentRules = $field['is_required'] ? ['required', 'array'] : ['nullable', 'array'];
                $fileRules   = ['file'];

                if (!empty($field['validation_rules'])) {
                    foreach (explode('|', $field['validation_rules']) as $rule) {
                        $rule = trim($rule);
                        if (str_starts_with($rule, 'max_files:')) {
                            $parentRules[] = 'max:' . (explode(':', $rule)[1] ?? 5);
                        } elseif (str_starts_with($rule, 'min_files:')) {
                            $parentRules[] = 'min:' . (explode(':', $rule)[1] ?? 1);
                        } else {
                            $fileRules[] = $rule;
                        }
                    }
                } else {
                    $fileRules[] = 'max:5120';
                }

                $rules[$key]        = $parentRules;
                $rules["{$key}.*"] = array_unique($fileRules);
            } elseif ($field['type'] === 'file') {
                $rules[$key] = array_merge(['file'], $this->getFieldValidationRules($field));
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
            foreach (explode('|', $field['validation_rules']) as $rule) {
                $rule = trim($rule);
                if (str_starts_with($rule, 'max_files:') || str_starts_with($rule, 'min_files:')) {
                    continue;
                }
                $rules[] = $rule;
            }
        } else {
            switch ($field['type']) {
                case 'email':          $rules[] = 'email';   break;
                case 'number':         $rules[] = 'numeric'; break;
                case 'url':            $rules[] = 'url';     break;
                case 'file':
                case 'file_multiple':  $rules[] = 'max:5120'; break;
            }
        }

        return array_values(array_unique($rules));
    }

    public function validationAttributes(): array
    {
        $attributes = [
            'department_id' => __('creators-ticketing::resources.frontend.select_department'),
            'form_id'       => __('creators-ticketing::resources.frontend.category_label'),
            'guest_name'    => __('creators-ticketing::resources.frontend.guest_name'),
            'guest_email'   => __('creators-ticketing::resources.frontend.guest_email'),
        ];

        foreach ($this->form_fields as $field) {
            $attributes["custom_fields.{$field['name']}"]    = $field['label'];
            $attributes["custom_fields.{$field['name']}.*"] = $field['label'] . ' (File)';
        }

        return $attributes;
    }

    // ── Submit ────────────────────────────────────────────────────────────────

    public function submit(): void
    {
        abort_unless(auth()->check() || $this->isGuestMode(), 403);
        $key = 'ticket-submit:' . (auth()->id() ?? request()->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('department_id', __('creators-ticketing::resources.frontend.too_many_submissions'));
            return;
        }
        RateLimiter::hit($key, 60);

        // Always validate against the current server-side form definition.
        $this->loadFormFields();
        $this->validate();
        $this->custom_fields = array_intersect_key($this->custom_fields, array_flip(array_column($this->form_fields, 'name')));

        // Spam check – passes guest email when not authenticated
        $spamData = array_merge($this->custom_fields, [
            'email' => auth()->check() ? auth()->user()->email : $this->guest_email,
        ]);
        $spamService = app(SpamFilterService::class);
        $spamCheck   = $spamService->checkTicket($spamData, auth()->user());

        if (!$spamCheck['allowed']) {
            session()->flash('error', $spamCheck['reason'] ?? 'Your submission was blocked by spam filters.');
            return;
        }

        // Open ticket limit — only applies to authenticated users
        if (auth()->check()) {
            $maxTickets = config('creators-ticketing.max_open_tickets_per_user');
            if ($maxTickets && $maxTickets > 0) {
                $openCount = Ticket::where('user_id', auth()->id())
                    ->whereHas('status', fn ($q) => $q->where('is_closing_status', false))
                    ->count();

                if ($openCount >= $maxTickets) {
                    session()->flash('error', config('creators-ticketing.ticket_limit_message'));
                    return;
                }
            }
        }

        $defaultStatus = TicketStatus::where('is_default_for_new', true)->first();

        // Strip file fields before initial create
        $tempCustomFields = $this->custom_fields;
        foreach ($this->form_fields as $field) {
            if (in_array($field['type'], ['file', 'file_multiple'])) {
                unset($tempCustomFields[$field['name']]);
            }
        }

        // Build ticket payload
        $payload = [
            'department_id'    => $this->department_id,
            'form_id'          => $this->form_id,
            'custom_fields'    => $tempCustomFields,
            'ticket_status_id' => $defaultStatus?->id,
            'last_activity_at' => now(),
        ];

        if ($this->isGuestMode()) {
            $token = bin2hex(random_bytes(32)); // 64 hex chars
            $payload['user_id']      = null;
            $payload['guest_name']   = $this->guest_name;
            $payload['guest_email']  = $this->guest_email;
            $payload['guest_token']  = $token;
        } else {
            $payload['user_id'] = auth()->id();
        }

        $ticket = Ticket::create($payload);

        // Process file uploads
        $finalCustomFields = $ticket->custom_fields ?? [];
        $hasFiles          = false;

        foreach ($this->form_fields as $field) {
            if (in_array($field['type'], ['file', 'file_multiple']) && !empty($this->custom_fields[$field['name']])) {
                $files = $this->custom_fields[$field['name']];
                if (!is_array($files)) {
                    $files = [$files];
                }
                $finalCustomFields[$field['name']] = TicketFileHelper::processUploadedFiles($files, $ticket->id);
                $hasFiles = true;
            }
        }

        if ($hasFiles) {
            $ticket->update(['custom_fields' => $finalCustomFields]);
        }

        $this->reset(['department_id', 'form_id', 'custom_fields', 'form_fields', 'available_forms',
                      'guest_name', 'guest_email']);

        if ($this->isGuestMode()) {
            // Show tracking link to guest instead of switching to the list tab
            $this->guestTrackingToken = $ticket->guest_token;
            $this->guestTrackingUrl   = route('creators-ticketing.guest.track', ['token' => $ticket->guest_token]);
            $this->showForm = false;
            $this->activeTab = 'new'; // stay on new-ticket tab so guest sees the success panel
            session()->flash('success', __('creators-ticketing::resources.frontend.guest_submitted'));
        } else {
            session()->flash('success', __('creators-ticketing::resources.frontend.submitted'));
            $this->showList();
            $this->loadUserTickets();
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** True when the form should operate in guest/anonymous mode. */
    public function isGuestMode(): bool
    {
        return config('creators-ticketing.allow_guest_tickets') && !auth()->check();
    }

    public function render()
    {
        return view('creators-ticketing::livewire.ticket-submit-form');
    }
}
