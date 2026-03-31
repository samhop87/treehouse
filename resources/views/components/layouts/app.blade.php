<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Treehouse' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-zinc-900 text-zinc-100 overflow-hidden select-none">
    {{-- Custom title bar (draggable region for frameless window) --}}
    <header
        class="flex items-center justify-between h-10 px-4 bg-zinc-950 border-b border-zinc-800"
        style="-webkit-app-region: drag;"
    >
        {{-- macOS traffic lights sit in the left ~70px, so add left padding --}}
        <div class="pl-16 text-sm font-medium text-zinc-400 truncate">
            {{ $titleBar ?? 'Treehouse' }}
        </div>
        <div class="flex items-center gap-2" style="-webkit-app-region: no-drag;">
            {{-- Slot for title bar actions (e.g., branch switcher, search) --}}
            {{ $titleBarActions ?? '' }}
        </div>
    </header>

    {{-- Main content area --}}
    <main class="h-[calc(100vh-2.5rem)] overflow-hidden">
        {{ $slot }}
    </main>

    {{-- Toast notifications --}}
    <div
        x-data="toastStack()"
        @toast.window="add($event.detail)"
        class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-show="toast.visible"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-2"
                class="pointer-events-auto px-4 py-2 rounded-lg shadow-lg text-xs font-medium max-w-xs"
                :class="toast.type === 'error'
                    ? 'bg-red-900/90 text-red-200 border border-red-800'
                    : 'bg-zinc-800/90 text-zinc-200 border border-zinc-700'"
                x-text="toast.message"
            ></div>
        </template>
    </div>

    @livewireScripts
</body>
</html>
