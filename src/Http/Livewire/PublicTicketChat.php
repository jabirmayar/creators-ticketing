<?php

namespace daacreators\CreatorsTicketing\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\RateLimiter;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\TicketReply;
use daacreators\CreatorsTicketing\Events\TicketReplyAdded;

class PublicTicketChat extends Component
{
    use WithFileUploads;

    #[Locked]
    public $ticketId;
    #[Locked]
    public $ticket;
    #[Locked]
    public $replies;
    public $message    = '';
    public $attachments = [];
    public $lastReplyId = null;

    // Passed when opened from the guest tracking page
    #[Locked]
    public ?string $guestToken = null;

    // ──────────────────────────────────────────────────────────────────────────

    public function mount($ticketId, ?string $guestToken = null)
    {
        $this->ticketId   = $ticketId;
        $this->guestToken = $guestToken;

        $this->authorizeTicket();

        if (!$guestToken && auth()->check()) {
            $this->ticket->markSeenBy(auth()->id());
        }

        $this->loadReplies();
    }

    public function loadReplies(): void
    {
        $this->authorizeTicket();
        $this->replies = $this->ticket->publicReplies()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        $lastReply     = $this->replies->last();
        $currentLastId = $lastReply ? $lastReply->id : 0;

        if ($this->lastReplyId !== null && $currentLastId > $this->lastReplyId) {
            $this->dispatch('scroll-to-bottom');
        }

        $this->lastReplyId = $currentLastId;

        if (!$this->guestToken && auth()->check()) {
            foreach ($this->replies as $reply) {
                $reply->markSeenBy(auth()->id());
            }
        }
    }

    public function sendMessage(): void
    {
        $this->authorizeTicket();
        if ($this->ticket->status?->is_closing_status) {
            $this->addError('message', __('creators-ticketing::resources.chat.ticket_closed'));
            return;
        }

        // Rate-limit key: prefer user ID, fall back to guest token + IP
        $key = auth()->check()
            ? 'ticket-message:' . auth()->id()
            : 'ticket-message-guest:' . $this->guestToken . ':' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('message', "You are sending messages too fast. Please wait {$seconds} seconds.");
            return;
        }

        RateLimiter::hit($key);

        $this->validate([
            'message' => 'required|string|max:5000',
        ]);

        // For guest replies we store a display name from ticket.guest_name
        // but user_id is null — the chat view handles null user gracefully.
        $reply = TicketReply::create([
            'ticket_id'        => $this->ticket->id,
            'user_id'          => $this->guestToken ? null : auth()->id(),
            'content'          => nl2br(e($this->message)),
            'is_internal_note' => false,
            'guest_name'       => $this->ticket->isGuestTicket() ? $this->ticket->guest_name : null,
        ]);

        event(new TicketReplyAdded($this->ticket, $reply));

        $this->ticket->forceFill(['last_activity_at' => now()])->saveQuietly();
        $this->ticket->markUnseen();

        $this->message = '';
        $this->loadReplies();
    }

    #[On('$refresh')]
    public function refresh(): void
    {
        $this->loadReplies();
    }

    public function render()
    {
        $this->loadReplies();
        return view('creators-ticketing::livewire.public-ticket-chat');
    }

    protected function authorizeTicket(): void
    {
        $query = Ticket::with(['department', 'status'])->whereKey($this->ticketId);
        if ($this->guestToken !== null) {
            abort_unless(preg_match('/\A[a-f0-9]{64}\z/', $this->guestToken), 404);
            $query->whereNull('user_id')->where('guest_token', $this->guestToken);
        } else {
            abort_unless(auth()->check(), 403);
            $query->where('user_id', auth()->id());
        }

        $ticket = $query->first();
        abort_unless($ticket, 404);
        $this->ticket = $ticket;
    }
}
