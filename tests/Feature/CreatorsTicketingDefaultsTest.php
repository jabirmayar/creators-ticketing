<?php

use Illuminate\Support\Facades\Route;

test('standalone support route is not registered by default', function () {
    expect(config('creators-ticketing.public_page_enabled'))->toBeFalse();
    expect(Route::has('creators-ticketing.support'))->toBeFalse();
    $this->get('/support')->assertNotFound();
});

test('guest tracking remains registered when the standalone support page is disabled', function () {
    expect(config('creators-ticketing.public_page_enabled'))->toBeFalse();
    expect(Route::has('creators-ticketing.guest.track'))->toBeTrue();
    expect(Route::has('creators-ticketing.attachment'))->toBeTrue();
});
