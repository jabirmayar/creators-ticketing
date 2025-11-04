<?php

namespace daacreators\CreatorsTicketing\Http\Livewire;

use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\TicketReply;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;

class PublicTicketChat extends Component
{
    use WithFileUploads;

    public $ticketId;
    public $ticket;
    public $replies;
    public $message = '';
    public $attachments = [];

    public function mount($ticketId)
    {
        $this->ticketId = $ticketId;
        $this->ticket = Ticket::with(['department', 'status'])
            ->where('id', $ticketId)
            ->where('user_id', auth()->id())
            ->firstOrFail();
    $this->ticket->markSeenBy(auth()->id());
    $this->loadReplies();
    }

    public function loadReplies()
    {
        $this->replies = $this->ticket->publicReplies()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($this->replies as $reply) {
            if (!$reply->is_seen && auth()->check()) {
                $reply->markSeenBy(auth()->id());
            }
        }
    }

    public function sendMessage()
    {
        $this->validate([
            'message' => 'required|string|max:10000',
        ]);

        TicketReply::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => auth()->id(),
            'content' => $this->message,
            'is_internal_note' => false,
        ]);

    $this->ticket->update(['last_activity_at' => now()]);
    $this->ticket->markSeenBy(auth()->id());

        $this->message = '';
        $this->loadReplies();
        $this->dispatch('scroll-to-bottom');
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
        return view('creators-ticketing::livewire.public-ticket-chat');
    }
}