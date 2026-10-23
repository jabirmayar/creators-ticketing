<?php

use App\Models\User;
use daacreators\CreatorsTicketing\Events\TicketReplyAdded;
use daacreators\CreatorsTicketing\Http\Livewire\PublicSupportPage;
use daacreators\CreatorsTicketing\Http\Livewire\PublicTicketChat;
use daacreators\CreatorsTicketing\Http\Livewire\TicketSubmitForm;
use daacreators\CreatorsTicketing\Models\Department;
use daacreators\CreatorsTicketing\Models\Form;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\TicketStatus;
use daacreators\CreatorsTicketing\Notifications\CustomerTicketNotification;
use daacreators\CreatorsTicketing\TicketingServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'creators-ticketing.public_page_enabled' => true,
        'creators-ticketing.allow_guest_tickets' => true,
        'creators-ticketing.customer_emails_enabled' => true,
    ]);
    // Route registration happens during boot; reload the package routes after opting in.
    require dirname((new ReflectionClass(TicketingServiceProvider::class))->getFileName(), 2).'/routes/web.php';
    app('router')->getRoutes()->refreshNameLookups();
    Notification::fake();
    $this->department = Department::create(['name' => 'Support', 'slug' => 'support']);
    $this->form = Form::create(['name' => 'General', 'slug' => 'general']);
    $this->form->fields()->create(['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'is_required' => true]);
    $this->department->forms()->attach($this->form);
    $this->status = TicketStatus::create(['name' => 'Open', 'slug' => 'open', 'is_default_for_new' => true]);
    $this->guestTicket = fn (array $attributes = []) => Ticket::create(array_merge([
        'department_id' => $this->department->id,
        'form_id' => $this->form->id,
        'guest_name' => 'Guest Customer',
        'guest_email' => 'customer@example.com',
        'guest_token' => bin2hex(random_bytes(32)),
        'custom_fields' => ['message' => 'Please help'],
    ], $attributes));
});

test('public support page works without a host layout or login route', function () {
    $this->get(route('creators-ticketing.support'))->assertOk()->assertSee('Your Email')->assertSee('livewire');
});

test('guest submission creates one ticket and sends exactly one confirmation', function () {
    Livewire::test(PublicSupportPage::class)
        ->set('guest_name', 'Guest Customer')
        ->set('guest_email', 'customer@example.com')
        ->set('department_id', $this->department->id)
        ->set('custom_fields.message', 'Please help')
        ->call('submit')->assertHasNoErrors()->assertSet('showForm', false);

    $ticket = Ticket::sole();
    expect($ticket->user_id)->toBeNull()->and(strlen($ticket->guest_token))->toBe(64);
    Notification::assertSentOnDemand(CustomerTicketNotification::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'customer@example.com'
        && $notification->trackingUrl === route('creators-ticketing.guest.track', ['token' => $ticket->guest_token])
        && $notification->reply === null);
    Notification::assertCount(1);
});

test('guest fields and dynamic required fields are validated', function () {
    Livewire::test(TicketSubmitForm::class)->set('department_id', $this->department->id)
        ->set('guest_email', 'invalid')->call('submit')
        ->assertHasErrors(['guest_name', 'guest_email', 'custom_fields.message']);
    expect(Ticket::count())->toBe(0);
    Notification::assertNothingSent();
});

test('internal departments and unrelated forms cannot receive public tickets', function () {
    $internal = Department::create(['name' => 'Internal', 'slug' => 'internal', 'visibility' => 'internal']);
    Livewire::test(TicketSubmitForm::class)->set('guest_name', 'Guest')->set('guest_email', 'customer@example.com')
        ->set('department_id', $internal->id)->call('submit')->assertHasErrors('department_id');
    $other = Form::create(['name' => 'Private form', 'slug' => 'private']);
    Livewire::test(TicketSubmitForm::class)->set('guest_name', 'Guest')->set('guest_email', 'customer@example.com')
        ->set('department_id', $this->department->id)->set('form_id', $other->id)
        ->call('submit')->assertHasErrors('form_id');
    expect(Ticket::count())->toBe(0);
});

test('guest mode cannot be bypassed by submitting an already mounted form', function () {
    $component = Livewire::test(TicketSubmitForm::class);
    config(['creators-ticketing.allow_guest_tickets' => false]);
    $component->call('submit')->assertForbidden();
    $this->get(route('creators-ticketing.support'))->assertForbidden();
});

test('guest submissions are limited per IP', function () {
    RateLimiter::hit('ticket-submit:127.0.0.1', 60);
    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit('ticket-submit:127.0.0.1', 60);
    }
    Livewire::test(TicketSubmitForm::class)->call('submit')->assertHasErrors('department_id');
    expect(Ticket::count())->toBe(0);
});

test('tracking page shows only public replies', function () {
    $ticket = ($this->guestTicket)();
    $ticket->replies()->create(['content' => 'Public answer', 'is_internal_note' => false]);
    $ticket->replies()->create(['content' => 'Secret internal note', 'is_internal_note' => true]);
    $this->get(route('creators-ticketing.guest.track', ['token' => $ticket->guest_token]))
        ->assertOk()->assertSee('Public answer')->assertDontSee('Secret internal note');
    $this->get(route('creators-ticketing.guest.track', ['token' => str_repeat('0', 64)]))
        ->assertOk()->assertSee('Ticket not found')->assertDontSee('customer@example.com');
});

test('guest chat rejects missing tokens and tokens belonging to another ticket', function () {
    $this->withExceptionHandling();
    $ticket = ($this->guestTicket)();
    $other = ($this->guestTicket)();
    Livewire::test(PublicTicketChat::class, ['ticketId' => $ticket->id])->assertForbidden();
    Livewire::test(PublicTicketChat::class, ['ticketId' => $ticket->id, 'guestToken' => $other->guest_token])->assertNotFound();
});

test('guest replies escape HTML and never trigger a customer email', function () {
    $ticket = ($this->guestTicket)();
    Notification::fake();
    Livewire::test(PublicTicketChat::class, ['ticketId' => $ticket->id, 'guestToken' => $ticket->guest_token])
        ->set('message', '<script>alert(1)</script>')->call('sendMessage')->assertHasNoErrors();
    expect($ticket->replies()->sole()->content)->toBe('&lt;script&gt;alert(1)&lt;/script&gt;');
    Notification::assertNothingSent();
});

test('token access stays guest access even with an unrelated logged in user', function () {
    $ticket = ($this->guestTicket)();
    $this->actingAs(User::factory()->create());
    Notification::fake();
    Livewire::test(PublicTicketChat::class, ['ticketId' => $ticket->id, 'guestToken' => $ticket->guest_token])
        ->set('message', 'Thanks')->call('sendMessage')->assertHasNoErrors();
    expect($ticket->replies()->sole()->user_id)->toBeNull();
    Notification::assertNothingSent();
});

test('closed tickets reject forged send actions', function () {
    $ticket = ($this->guestTicket)();
    $this->status->update(['is_closing_status' => true]);
    Livewire::test(PublicTicketChat::class, ['ticketId' => $ticket->id, 'guestToken' => $ticket->guest_token])
        ->set('message', 'New message')->call('sendMessage')->assertHasErrors('message');
    expect($ticket->replies()->count())->toBe(0);
});

test('public staff reply emails the customer but internal notes do not', function () {
    $ticket = ($this->guestTicket)();
    $agent = User::factory()->create();
    Notification::fake();
    $reply = $ticket->replies()->create(['user_id' => $agent->id, 'content' => '<p>We can help.</p>', 'is_internal_note' => false]);
    event(new TicketReplyAdded($ticket, $reply));
    $note = $ticket->replies()->create(['user_id' => $agent->id, 'content' => 'Secret', 'is_internal_note' => true]);
    event(new TicketReplyAdded($ticket, $note));
    Notification::assertSentOnDemand(CustomerTicketNotification::class, fn ($notification, $channels, $notifiable) => $notification->reply === '<p>We can help.</p>' && $notifiable->routes['mail'] === 'customer@example.com');
    Notification::assertCount(1);
});

test('registered customers receive emails with their own support link', function () {
    $this->withExceptionHandling();
    $customer = User::factory()->create();
    $ticket = ($this->guestTicket)(['user_id' => $customer->id, 'guest_token' => null]);
    Notification::assertSentOnDemand(CustomerTicketNotification::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === $customer->email
        && $notification->trackingUrl === route('creators-ticketing.support', ['ticket' => $ticket->id]));
    Notification::fake();
    $reply = $ticket->replies()->create(['user_id' => $customer->id, 'content' => 'My reply', 'is_internal_note' => false]);
    event(new TicketReplyAdded($ticket, $reply));
    Notification::assertNothingSent();
    Livewire::actingAs(User::factory()->create())->test(PublicTicketChat::class, ['ticketId' => $ticket->id])->assertNotFound();
});

test('customer email delivery is opt in', function () {
    config(['creators-ticketing.customer_emails_enabled' => false]);
    ($this->guestTicket)();
    Notification::assertNothingSent();
});

test('notification renders safe reply text and the tracking action', function () {
    $notification = new CustomerTicketNotification('TKT-123', 'https://example.com/tickets/track/secret',
        '<p>Hello <strong>customer</strong></p><img src="https://example.com/private/ticket-attachments/1/private.png"><script>alert(1)</script>');
    $mail = $notification->toMail(new AnonymousNotifiable);
    $html = (string) $mail->render();
    expect($html)->toContain('Hello customer')->toContain('TKT-123')->toContain('https://example.com/tickets/track/secret')
        ->not->toContain('<script>')->not->toContain('private.png');
});

test('guest tracking tokens cannot download private attachments', function () {
    $ticket = ($this->guestTicket)();
    $this->get(route('creators-ticketing.attachment', ['ticketId' => $ticket->id, 'filename' => 'secret.txt', 'token' => $ticket->guest_token]), ['Accept' => 'application/json'])
        ->assertUnauthorized();
});

test('chat credentials cannot be replaced through Livewire updates', function () {
    $ticket = ($this->guestTicket)();
    $component = Livewire::test(PublicTicketChat::class, ['ticketId' => $ticket->id, 'guestToken' => $ticket->guest_token]);
    expect(fn () => $component->set('guestToken', str_repeat('0', 64)))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('revoked tracking tokens stop an already open chat', function () {
    $this->withExceptionHandling();
    $ticket = ($this->guestTicket)();
    $component = Livewire::test(PublicTicketChat::class, ['ticketId' => $ticket->id, 'guestToken' => $ticket->guest_token]);
    $ticket->updateQuietly(['guest_token' => bin2hex(random_bytes(32))]);
    $component->set('message', 'No longer authorized')->assertNotFound();
    expect($ticket->replies()->count())->toBe(0);
});

test('guest chat rate limits repeated messages', function () {
    $ticket = ($this->guestTicket)();
    $component = Livewire::test(PublicTicketChat::class, ['ticketId' => $ticket->id, 'guestToken' => $ticket->guest_token]);
    for ($i = 0; $i < 5; $i++) {
        $component->set('message', 'Message '.$i)->call('sendMessage')->assertHasNoErrors();
    }
    $component->set('message', 'Too many')->call('sendMessage')->assertHasErrors('message');
    expect($ticket->replies()->count())->toBe(5);
});

test('real notification dispatch queues mail for delivery after commit', function () {
    Notification::swap(new ChannelManager(app()));
    Queue::fake();
    ($this->guestTicket)();
    Queue::assertPushed(SendQueuedNotifications::class,
        fn ($job) => $job->notification instanceof CustomerTicketNotification
            && $job->notification->afterCommit === true && $job->channels === ['mail']);
});

test('a notification transport failure does not reject a saved guest ticket', function () {
    Exceptions::fake();
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Mail transport unavailable'));
    Livewire::test(PublicSupportPage::class)->set('guest_name', 'Customer')->set('guest_email', 'customer@example.com')
        ->set('department_id', $this->department->id)->set('custom_fields.message', 'Please help')
        ->call('submit')->assertHasNoErrors()->assertSet('showForm', false);
    expect(Ticket::count())->toBe(1);
    Exceptions::assertReported(RuntimeException::class);
});

test('existing authenticated embedded form submission still works with guest mode disabled', function () {
    $customer = User::factory()->create();
    $this->actingAs($customer);
    config(['creators-ticketing.allow_guest_tickets' => false, 'creators-ticketing.customer_emails_enabled' => false]);
    Livewire::test(TicketSubmitForm::class)->set('department_id', $this->department->id)
        ->set('custom_fields.message', 'Authenticated ticket')->call('submit')
        ->assertHasNoErrors()->assertSet('activeTab', 'list');
    $ticket = Ticket::sole();
    expect($ticket->user_id)->toBe($customer->id)->and($ticket->guest_token)->toBeNull();
    Notification::assertNothingSent();
    Livewire::test(TicketSubmitForm::class)->call('viewTicket', $ticket->id)
        ->assertSet('selectedTicket.id', $ticket->id);
});

test('agents can mark a guest reply as seen', function () {
    $ticket = ($this->guestTicket)();
    $reply = $ticket->replies()->create(['content' => 'Guest message', 'is_internal_note' => false]);
    $agent = User::factory()->create();
    $reply->markSeenBy($agent->id);
    expect($reply->fresh()->is_seen)->toBeTrue()->and($reply->fresh()->seen_by)->toBe($agent->id);
});
