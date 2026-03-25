<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Navigation group name for Ticketing resources
    |--------------------------------------------------------------------------
    |
    | All Filament resources in the Ticketing module will be grouped under
    | this name in the sidebar navigation.
    |
    */
    'navigation_group' => env('TICKETING_NAV_GROUP', 'Creators Ticketing'),

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    */
    'user_model' => env('USER_MODEL', \App\Models\User::class),

    /*
    |--------------------------------------------------------------------------
    | User ID / Foreign Key Type
    |--------------------------------------------------------------------------
    |
    | By default, this package will infer the user ID type from your user model:
    | - int/bigint => foreignId (unsignedBigInteger)
    | - HasUlids   => ULID (char(26))
    | - otherwise  => UUID
    |
    | If your user primary key is a custom string (e.g. nanoid), you should
    | configure the type and length below so migrations can create matching
    | foreign keys.
    |
    | Supported types: int, bigint, uuid, ulid, string, char
    |
    */
    'user_table' => env('TICKETING_USER_TABLE', null),
    'user_key' => env('TICKETING_USER_KEY', null),

    'user_key_column' => [
        'type' => env('TICKETING_USER_KEY_TYPE', null),
        'length' => env('TICKETING_USER_KEY_LENGTH', null),
        'collation' => env('TICKETING_USER_KEY_COLLATION', null),
    ],

    /*
    | Override user key settings per foreign key column name.
    |
    | Example:
    | 'user_foreign_keys' => [
    |     'user_id' => ['type' => 'uuid'],
    |     'assignee_id' => ['type' => 'char', 'length' => 21], // nanoid
    | ],
    */
    'user_foreign_keys' => [],

    /*
    |--------------------------------------------------------------------------
    | User Name from User Model
    | Default to 'name'
    |--------------------------------------------------------------------------
    */
    'user_name_column' => env('USER_NAME_COLUMN', 'name'),

    /*
    |--------------------------------------------------------------------------
    | Navigation Visibility Rules ( Main Admin Panel )
    |--------------------------------------------------------------------------
    |
    | Define which users can see all resources.
    | Example:
    | 'field' => 'email',
    | 'allowed' => ['admin@site.com', 'manager@site.com']
    |
    | or:
    | 'field' => 'role_id',
    | 'allowed' => [1, 2]
    | Example (.env):
    |   TICKETING_NAV_FIELD=email
    |   TICKETING_NAV_ALLOWED=admin@demo.com,demo@gmail.com
    |
    */
    'navigation_visibility' => [
        'field' => env('TICKETING_NAV_FIELD', 'email'),
        'allowed' => array_filter(
            array_map('trim', explode(',', env('TICKETING_NAV_ALLOWED', 'admin@demo.com')))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ticket Assignment Scope
    |--------------------------------------------------------------------------
    |
    | Define who tickets can be assigned to:
    | - 'department_only' => Tickets can only be assigned to users within
    |   the ticket's department.
    | - 'any_user' => Tickets can be assigned to any user in the system.
    |
    */
    'ticket_assign_scope' => env('TICKET_ASSIGN_SCOPE', 'any_user'),

    /*
    |--------------------------------------------------------------------------
    | Ticket Creation Limits
    |--------------------------------------------------------------------------
    |
    | Limit how many open/active tickets a user can have at once.
    | Set to null or 0 for unlimited tickets.
    |
    */
    'max_open_tickets_per_user' => env('MAX_OPEN_TICKETS_PER_USER', 5),

    /*
    |--------------------------------------------------------------------------
    | Ticket Limit Error Message
    |--------------------------------------------------------------------------
    |
    | The message shown when a user reaches their ticket limit.
    |
    */
    'ticket_limit_message' => env('TICKET_LIMIT_MESSAGE', 'You have reached the maximum number of open tickets. Please wait for an existing ticket to be resolved before creating a new one.'),


    /*
    |--------------------------------------------------------------------------
    | Ticket Spam filters
    |--------------------------------------------------------------------------
    |
    | This is for spam filter to protect against spam tickets
    |
    */
    'spam_protection' => [
        'enabled' => env('TICKETING_SPAM_PROTECTION', true),
        'rate_limiting' => [
            'enabled' => true,
            'max_tickets_per_hour' => 5,
            'max_tickets_per_day' => 20,
        ],
        'content_filtering' => [
            'enabled' => true,
            'check_links' => true,
            'max_links_allowed' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ticket Prefix
    |--------------------------------------------------------------------------
    |
    | This is the prefix used before each generated ticket ID.
    | Example: TKT -> TKT-251014-DFSTDE
    |
    */
    'ticket_prefix' => env('TICKET_PREFIX', 'TKT'),

    /*
    |--------------------------------------------------------------------------
    | Tickets Tables Prefix
    |--------------------------------------------------------------------------
    |
    | This is the prefix used before each generated ticket table name to avoid collisions.
    |
    */
    'table_prefix' => env('TICKETING_TABLE_PREFIX', 'ct_'),

    /*
    |--------------------------------------------------------------------------
    | Ticket UID Format
    |--------------------------------------------------------------------------
    |
    | Define your own format using placeholders:
    | {PREFIX} - your prefix (from ticket_prefix)
    | {DATE}   - formatted date (ymd or any format you choose below)
    | {RAND}   - random string (length defined below)
    |
    | Example:
    | '{PREFIX}-{DATE}-{RAND}'  => TKT-251014-DFSTDE
    | '{PREFIX}_{RAND}'         => TKT_DFSTDE
    | '{DATE}-{RAND}'           => 251014-DFSTDE
    |
    */
    'ticket_format' => env('TICKET_FORMAT', '{PREFIX}-{DATE}-{RAND}'),

    /*
    |--------------------------------------------------------------------------
    | Date Format
    |--------------------------------------------------------------------------
    |
    | Choose how the date part should appear in your ticket IDs.
    | Examples: ymd, Y-m-d, YmdHis, etc.
    |
    */
    'ticket_date_format' => env('TICKET_DATE_FORMAT', 'ymd'),

    /*
    |--------------------------------------------------------------------------
    | Random String Length
    |--------------------------------------------------------------------------
    |
    | The number of random characters appended at the end.
    |
    */
    'ticket_random_length' => env('TICKET_RANDOM_LENGTH', 4),

];
