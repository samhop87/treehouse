@props(['message', 'operation'])

<div
    {{ $attributes->class('fixed inset-0 z-[100] items-center justify-center bg-[#06060c]/75 backdrop-blur-[2px]') }}
    role="dialog"
    aria-modal="true"
    aria-label="{{ $message }}"
    aria-busy="true"
    data-operation-modal="{{ $operation }}"
>
    <div class="flex min-w-72 flex-col items-center rounded-xl border border-[#3a3e4a] bg-[#1a1e27] px-10 py-8 text-center shadow-2xl shadow-black/60">
        <svg class="h-9 w-9 animate-spin text-violet-500" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="mt-4 text-sm font-semibold text-gray-100">{{ $message }}</p>
        <p class="mt-1 text-xs text-gray-500">Please wait&hellip;</p>
    </div>
</div>
