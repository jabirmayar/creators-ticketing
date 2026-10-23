<?php

namespace daacreators\CreatorsTicketing\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\Locked;
use daacreators\CreatorsTicketing\Models\Ticket;

/**
 * Standalone Livewire page shown to guests when they follow their tracking link.
 * Mount it on a dedicated route — no auth middleware required.
 */
class GuestTicketView extends Component
{
    #[Locked]
    public string $token;
    #[Locked]
    public ?Ticket $ticket = null;
    #[Locked]
    public bool $found = false;

    public function mount(string $token): void
    {
        $this->token  = $token;
        $this->loadTicket();
    }

    protected function loadTicket(): void
    {
        $this->ticket = Ticket::with(['department', 'status'])
            ->whereNull('user_id')
            ->where('guest_token', $this->token)
            ->first();

        $this->found = $this->ticket !== null;
    }

    public function render()
    {
        $this->loadTicket();
        return view('creators-ticketing::livewire.guest-ticket-view')
            ->layout(config('creators-ticketing.guest_layout', 'creators-ticketing::layouts.public'));
    }
}
