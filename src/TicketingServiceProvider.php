<?php

namespace daacreators\CreatorsTicketing;

use Livewire\Livewire;
use Filament\Support\Assets\Css;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentAsset;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Observers\TicketObserver;
use daacreators\CreatorsTicketing\Http\Livewire\TicketTimeline;
use daacreators\CreatorsTicketing\Http\Livewire\PublicTicketChat;
use daacreators\CreatorsTicketing\Http\Livewire\TicketSubmitForm;
use daacreators\CreatorsTicketing\Http\Livewire\TicketChatMessages;
use daacreators\CreatorsTicketing\Filament\Widgets\TicketStatsWidget;
use daacreators\CreatorsTicketing\Http\Livewire\TicketAttachmentsDisplay;

class TicketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/creators-ticketing.php', 'creators-ticketing'
        );
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'creators-ticketing');

        $this->publishes([
            __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/creators-ticketing'),
        ], 'creators-ticketing-translations');

        $this->publishes([
            __DIR__.'/../config/creators-ticketing.php' => config_path('creators-ticketing.php'),
        ], 'creators-ticketing-config');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Ticket::observe(TicketObserver::class);

        $this->registerLivewireComponent();

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $this->registerPrivateDisk();

        $this->ensureStorageDirectoryExists();
        
        $this->registerEvents();
    }

    protected function registerEvents(): void
    {
        
    }

    protected function registerPrivateDisk(): void
    {
        app('config')->set('filesystems.disks.private', [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'visibility' => 'private',
            'throw' => false,
        ]);

        app()->forgetInstance('filesystem');
    }

    protected function ensureStorageDirectoryExists(): void
    {
        $directory = storage_path('app/private/ticket-attachments');

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        chmod($directory, 0755);
    }

    protected function registerLivewireComponent(): void
    {
        FilamentAsset::register([
            Css::make('creators-ticketing', __DIR__ . '/../resources/dist/app.css'),
        ], 'daacreators/creators-ticketing');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'creators-ticketing');

        Livewire::component('creators-ticketing::ticket-submit-form', TicketSubmitForm::class);

        Livewire::component('creators-ticketing::public-ticket-chat', PublicTicketChat::class);
        
        Livewire::component('creators-ticketing::ticket-chat-messages', TicketChatMessages::class);

        Livewire::component('creators-ticketing::ticket-attachments-display', TicketAttachmentsDisplay::class);

        Livewire::component('creators-ticketing::ticket-timeline', TicketTimeline::class);

        Livewire::component('creators-ticketing.filament.widgets.ticket-stats-widget', TicketStatsWidget::class);
        
    }
}