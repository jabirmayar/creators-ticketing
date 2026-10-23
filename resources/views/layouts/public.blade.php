<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('creators-ticketing::resources.frontend.support_title') }} - {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ \Filament\Support\Facades\FilamentAsset::getStyleHref('creators-ticketing', 'daacreators/creators-ticketing') }}">
    @livewireStyles
</head>
<body class="bg-gray-50 text-gray-900">
    {{ $slot }}
    @livewireScripts
</body>
</html>
