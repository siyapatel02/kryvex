<!DOCTYPE html>
<html lang="en" class="h-full bg-zinc-950">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Kryvex Admin Tab Favicon Icon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.png') }}">
    <link rel="shortcut icon" href="{{ asset('logo.png') }}">

    <title>@yield('title', 'Kryvex Admin Portal')</title>

    @vite(['resources/css/app.css', 'resources/css/common/toast.css', 'resources/js/app.js', 'resources/js/common/toast.js'])
    @stack('styles')
</head>

<body class="h-full bg-zinc-950 text-white font-sans antialiased selection:bg-amber-400 selection:text-black min-h-screen">
    <main class="min-h-screen">
        @yield('content')
    </main>

    @stack('scripts')
</body>

</html>
