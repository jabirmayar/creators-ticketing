<?php

namespace daacreators\CreatorsTicketing\Http\Livewire;

use Livewire\Component;

class TicketAttachmentsDisplay extends Component
{
    public $ticketId;
    public $files;
    public $label;

    public function mount($ticketId, $files, $label = null)
    {
        $this->ticketId = $ticketId;
        $this->files = is_array($files) ? $files : [$files];
        $this->label = $label;
    }

    public function render()
    {
        return view('creators-ticketing::livewire.ticket-attachments-display');
    }
}
