<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="E-SAKIP Kabupaten Banjarnegara untuk transparansi perencanaan, pengukuran, pelaporan, dan evaluasi kinerja perangkat daerah.">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" sizes="any" href="/favicon.ico?v=20260609">
        <link rel="apple-touch-icon" href="/images/logo-banjarnegara-180.png?v=20260609">

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
