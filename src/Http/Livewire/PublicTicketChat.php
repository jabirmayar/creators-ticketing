<?php

namespace daacreators\CreatorsTicketing\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\RateLimiter;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\TicketReply;
use daacreators\CreatorsTicketing\Events\TicketReplyAdded;

class PublicTicketChat extends Component
{
    use WithFileUploads;

    public $ticketId;
    public $ticket;
    public $replies;
    public $message = '';
    public $attachments = [];
    
    public $lastReplyId = null; 

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

        $lastReply = $this->replies->last();
        $currentLastId = $lastReply ? $lastReply->id : 0;

        if ($this->lastReplyId !== null && $currentLastId > $this->lastReplyId) {
            $this->dispatch('scroll-to-bottom');
        }

        $this->lastReplyId = $currentLastId;

        if (auth()->check()) {
            foreach ($this->replies as $reply) {
                $reply->markSeenBy(auth()->id());
            }
        }
    }

    public function sendMessage()
    {
        $key = 'ticket-message:' . auth()->id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('message', "You are sending messages too fast. Please wait $seconds seconds.");
            return;
        }
        
        RateLimiter::hit($key);

        $this->validate([
            'message' => 'required|string|max:5000', 
        ]);

        $cleanMessage = strip_tags($this->message);

        $reply = TicketReply::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => auth()->id(),
            'content' => $this->message,
            'is_internal_note' => false,
        ]);

        event(new TicketReplyAdded($this->ticket, $reply));
        
        $this->message = '';
        
        $this->loadReplies(); 
    }

    #[On('$refresh')]
    public function refresh()
    {
        $this->loadReplies();
    }

    public function render()
    {
        $this->loadReplies();
        return view('creators-ticketing::livewire.public-ticket-chat');
    }
}