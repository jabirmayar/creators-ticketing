<?php

namespace daacreators\CreatorsTicketing\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class TicketChatMessages extends Component implements HasForms
{
    use InteractsWithForms;

    public $ticket;
    public $replies;
    public bool $canViewInternalNotes = false;

    public function mount($ticket, $canViewInternalNotes = false)
    {
        $this->ticket = $ticket;
        $this->canViewInternalNotes = $canViewInternalNotes;
        $this->loadReplies();
    }

    public function loadReplies()
    {
        $this->replies = $this->ticket->publicReplies()
            ->with('user', 'ticket')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($this->replies as $reply) {
            if (!$reply->is_seen && auth()->check()) {
                $reply->markSeenBy(auth()->id());
            }
        }
    }

    #[On('$refresh')]
    public function refresh()
    {
        $this->loadReplies();
        $this->dispatch('scroll-to-bottom');
    }

    public function render()
    {
        $this->loadReplies(); 
        return view('creators-ticketing::livewire.ticket-chat-messages');
    }
}