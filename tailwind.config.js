/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './resources/**/*.blade.php',
        './vendor/daacreators/creators-ticketing/resources/views/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './app/**/*.php',
    ],
    theme: {
        extend: {},
    },
    plugins: [],
}