<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Keep the document background aligned with the editable runtime palette. --}}
        <style>
            html {
                background-color: var(--background, #fff);
            }

            html.dark {
                background-color: var(--background, #1b1e1c);
            }
        </style>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <link rel="stylesheet" href="{{ asset('theme-colors.css') }}?v={{ substr(hash_file('sha256', public_path('theme-colors.css')), 0, 12) }}">
        <x-inertia::head>
            <title>{{ config('app.name', 'Guixa') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
