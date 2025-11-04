# Creators Ticketing for Filament v4

[![Latest Version on Packagist](https://img.shields.io/packagist/v/daacreators/creators-ticketing.svg)](https://packagist.org/packages/daacreators/creators-ticketing)
[![Total Downloads](https://img.shields.io/packagist/dt/daacreators/creators-ticketing.svg)](https://packagist.org/packages/daacreators/creators-ticketing)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

A robust and dynamic ticketing system plugin for Filament 4, providing a complete helpdesk solution for your Laravel application.

## Features

- Full ticketing system with departments and forms
- Agent management with department assignments
- Custom form builder for ticket submissions
- Real-time ticket chat using Livewire
- Ticket statistics dashboard widget
- Granular permission system
- File attachments support
- Responsive design
- Seamless integration with Filament 4

## Requirements

- PHP 8.2 or higher
- Laravel 11.x|12.x
- Filament 4.1.7 or higher
- Livewire 3.x

## Installation

You can install the package via composer:

```bash
composer require daacreators/creators-ticketing
```

After installation, publish the config file:

```bash
php artisan vendor:publish --tag="creators-ticketing-config"
```

Run the migrations:

```bash
php artisan migrate
```

## Configuration

### Basic Configuration

Configure the package by setting values in your `.env` file or directly in the `config/creators-ticketing.php` file:

```php
// Navigation group name in Filament sidebar
TICKETING_NAV_GROUP="Creators Ticketing"

// User model (if different from default)
USER_MODEL="\App\Models\User"

// Navigation visibility rules
TICKETING_NAV_FIELD=email
TICKETING_NAV_ALLOWED=admin@demo.com,manager@demo.com
```

### Navigation Visibility

You can control who sees the ticketing resources in the admin panel by configuring the navigation visibility rules:

```php
'navigation_visibility' => [
    'field' => 'email', // or any other field like role_id
    'allowed' => ['admin@site.com', 'manager@site.com']
],
```

## Usage

### Creating Forms

1. Go to the Forms section in the admin panel
2. Create a new form with custom fields

### Setting Up Departments

1. Navigate to the Filament admin panel
2. Go to the Departments section
3. Create departments and assign agents
4. Assign the form to specific departments

### Managing Tickets

Tickets can be managed through the Filament admin panel. You can:
- View all tickets
- Assign tickets to agents
- Change ticket status
- Add internal notes
- Communicate with users
- Track ticket activities

### Frontend Integration

To add the tickets and ticket submission form to your frontend:
 
```blade
\\ Add to your blade file
@livewire('creators-ticketing::ticket-submit-form')
```

## Dashboard Widget

The package includes a ticket statistics widget. Add it to your Filament dashboard:

```php
use daacreators\CreatorsTicketing\Filament\Widgets\TicketStatsWidget;

class DashboardConfig extends Config
{
    public function widgets(): array
    {
        return [
            TicketStatsWidget::class,
        ];
    }
}
```

## Security

The package includes built-in security features:
- Private file storage for attachments
- Permission-based access control
- Department-level agent restrictions

## Contributing

Thank you for considering contributing to Creators Ticketing! You can contribute in the following ways:

1. Report bugs
2. Submit feature requests
3. Submit pull requests
4. Improve documentation

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Jabir Khan](https://github.com/jabirmayar)
- [All Contributors](../../contributors)

## Support

If you discover any security-related issues, please email hello@jabirkhan.com.

**Built with ❤️ by [DAA Creators](https://daacreators.com)**
