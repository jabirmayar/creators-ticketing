<?php

namespace daacreators\CreatorsTicketing\Http\Livewire;

class PublicSupportPage extends TicketSubmitForm
{
    public function render()
    {
        return parent::render()
            ->layout(config('creators-ticketing.guest_layout'));
    }
}
