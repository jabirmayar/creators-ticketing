<?php

namespace daacreators\CreatorsTicketing\Http\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class TicketTimeline extends Component implements HasForms
{
    use InteractsWithForms, WithPagination;

    public $ticket;
    public $limit = null;
    public $perPage = 10;

    public function mount($ticket, $limit = null)
    {
        $this->ticket = $ticket;
        $this->limit = $limit;
    }

    public function getActivitiesProperty()
    {
        $query = $this->ticket->activities()
            ->with('user')
            ->orderBy('created_at', 'desc');
        
        if ($this->limit) {
            return $query->limit($this->limit)->get();
        }
        
        return $query->simplePaginate($this->perPage);
    }

    #[On('activity-added')]
    public function refreshTimeline()
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('creators-ticketing::livewire.ticket-timeline', [
            'activities' => $this->activities
        ]);
    }
}