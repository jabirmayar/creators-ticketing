<?php

use daacreators\CreatorsTicketing\TicketingServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('guest migrations preserve existing user IDs and foreign keys through upgrade and rollback', function (string $type, int|string $userId) {
    config(['creators-ticketing.table_prefix' => 'upgrade_']);
    Schema::create('upgrade_users', function (Blueprint $table) use ($type) {
        $table->{$type}('id')->primary();
    });
    foreach (['upgrade_tickets', 'upgrade_ticket_replies'] as $name) {
        Schema::create($name, function (Blueprint $table) use ($type) {
            $table->id();
            $table->{$type}('user_id');
            $table->foreign('user_id')->references('id')->on('upgrade_users')->cascadeOnDelete();
        });
    }
    DB::table('upgrade_users')->insert(['id' => $userId]);
    DB::table('upgrade_tickets')->insert(['user_id' => $userId]);
    DB::table('upgrade_ticket_replies')->insert(['user_id' => $userId]);
    $originalType = Schema::getColumnType('upgrade_tickets', 'user_id');
    $originalKeys = Schema::getForeignKeys('upgrade_tickets');
    $package = dirname((new ReflectionClass(TicketingServiceProvider::class))->getFileName(), 2);
    $tickets = require $package.'/database/migrations/2026_06_23_000001_add_guest_fields_to_tickets_table.php';
    $replies = require $package.'/database/migrations/2026_06_23_000002_add_guest_fields_to_ticket_replies_table.php';
    $tickets->up();
    $replies->up();
    expect(Schema::getColumnType('upgrade_tickets', 'user_id'))->toBe($originalType)
        ->and(Schema::getForeignKeys('upgrade_tickets'))->toBe($originalKeys)
        ->and((string) DB::table('upgrade_tickets')->value('user_id'))->toBe((string) $userId);
    $guestId = DB::table('upgrade_tickets')->insertGetId(['user_id' => null, 'guest_token' => str_repeat('a', 64)]);
    $replyId = DB::table('upgrade_ticket_replies')->insertGetId(['user_id' => null, 'guest_name' => 'Guest']);
    expect(fn () => $tickets->down())->toThrow(RuntimeException::class, 'Cannot roll back guest support');
    expect(fn () => $replies->down())->toThrow(RuntimeException::class, 'Cannot roll back guest support');
    expect(Schema::hasColumn('upgrade_tickets', 'guest_token'))->toBeTrue();
    DB::table('upgrade_ticket_replies')->where('id', $replyId)->delete();
    DB::table('upgrade_tickets')->where('id', $guestId)->delete();
    $replies->down();
    $tickets->down();
    expect(Schema::hasColumn('upgrade_tickets', 'guest_token'))->toBeFalse()
        ->and(Schema::getColumnType('upgrade_tickets', 'user_id'))->toBe($originalType)
        ->and(collect(Schema::getColumns('upgrade_tickets'))->firstWhere('name', 'user_id')['nullable'])->toBeFalse();
    DB::table('upgrade_users')->where('id', $userId)->delete();
    expect(DB::table('upgrade_tickets')->count())->toBe(0)
        ->and(DB::table('upgrade_ticket_replies')->count())->toBe(0);
})->with([
    'integer' => ['bigInteger', 123],
    'uuid' => ['uuid', '123e4567-e89b-12d3-a456-426614174000'],
    'ulid' => ['ulid', '01ARZ3NDEKTSV4RRFFQ69G5FAV'],
    'custom string' => ['string', 'custom-user-key'],
    'custom char' => ['char', 'custom-fixed-user-key'],
]);
