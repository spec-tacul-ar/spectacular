<x-layout :title="'Authentication error - ' . config('app.name', 'Spectacular')">
    <x-slot:head>
        @vite(['resources/css/main.css'])
    </x-slot:head>

    <main class="flex min-h-screen items-center justify-center p-4">
        <section class="w-full max-w-lg rounded-lg bg-white p-4 text-center shadow-lg sm:p-8 dark:bg-gray-900">
            <h1 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Authentication error</h1>
            <p class="mb-6 text-gray-600 dark:text-gray-400">{{ $message }}</p>
            <a href="/" class="btn btn-primary">Return home</a>
        </section>
    </main>
</x-layout>
