<div class="flex items-center justify-center h-full">
    <div class="text-center space-y-6 max-w-md w-full px-6">
        {{-- Header --}}
        <div>
            <h1 class="text-2xl font-bold text-zinc-100">Connect to GitHub</h1>
            <p class="mt-1 text-sm text-zinc-500">
                Sign in with your GitHub account to clone private repositories.
            </p>
        </div>

        {{-- IDLE: Show connect button --}}
        @if ($state === 'idle')
            <div class="space-y-4">
                <button
                    wire:click="startAuth"
                    class="w-full px-6 py-3 bg-zinc-800 hover:bg-zinc-700 text-zinc-100 font-medium rounded-lg border border-zinc-700 transition-colors duration-150 cursor-pointer flex items-center justify-center gap-3"
                >
                    {{-- GitHub icon --}}
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/>
                    </svg>
                    Sign in with GitHub
                </button>

                <a href="/" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-400 transition-colors">
                    Skip for now
                </a>
            </div>
        @endif

        {{-- REQUESTING: Loading --}}
        @if ($state === 'requesting')
            <div class="flex items-center justify-center gap-3 py-6 text-zinc-400">
                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span class="text-sm">Connecting to GitHub...</span>
            </div>
        @endif

        {{-- AWAITING: Show the code and poll via Alpine.js setTimeout --}}
        @if ($state === 'awaiting')
            <div
                x-data="{ timer: null }"
                x-init="
                    const poll = () => {
                        $wire.pollToken().then(() => {
                            if ($wire.state === 'awaiting') {
                                timer = setTimeout(poll, $wire.pollInterval * 1000);
                            }
                        }).catch(() => {
                            if ($wire.state === 'awaiting') {
                                timer = setTimeout(poll, 15000);
                            }
                        });
                    };
                    timer = setTimeout(poll, $wire.pollInterval * 1000);
                "
                x-effect="if ($wire.state !== 'awaiting') clearTimeout(timer)"
                class="space-y-5"
            >
                <div class="space-y-2">
                    <p class="text-sm text-zinc-400">Enter this code on GitHub:</p>
                    <div
                        x-data="{ copied: false }"
                        class="relative"
                    >
                        <div class="bg-zinc-800 border border-zinc-700 rounded-lg px-6 py-4 font-mono text-3xl font-bold text-zinc-100 tracking-[0.3em] select-text">
                            {{ $userCode }}
                        </div>
                        <button
                            x-on:click="
                                navigator.clipboard.writeText('{{ $userCode }}');
                                copied = true;
                                setTimeout(() => copied = false, 2000);
                            "
                            class="absolute top-2 right-2 px-2 py-1 text-xs text-zinc-400 hover:text-zinc-200 bg-zinc-700 hover:bg-zinc-600 rounded transition-colors cursor-pointer"
                        >
                            <span x-show="!copied">Copy</span>
                            <span x-show="copied" x-cloak>Copied!</span>
                        </button>
                    </div>
                </div>

                <div class="space-y-3">
                    <button
                        wire:click="openGitHub"
                        class="w-full px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-medium rounded-lg transition-colors duration-150 cursor-pointer"
                    >
                        Open GitHub in Browser
                    </button>
                    <p class="text-xs text-zinc-600">
                        Or go to <span class="text-zinc-400 font-mono">{{ $verificationUri }}</span>
                    </p>
                </div>

                <div class="flex items-center justify-center gap-2 py-2 text-zinc-500">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span class="text-xs">Waiting for authorization...</span>
                </div>
            </div>
        @endif

        {{-- SUCCESS --}}
        @if ($state === 'success')
            <div class="space-y-5">
                <div class="flex items-center justify-center">
                    <div class="w-12 h-12 bg-emerald-600/20 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>
                <div>
                    <p class="text-lg font-medium text-zinc-100">Connected to GitHub</p>
                    <p class="text-sm text-zinc-500 mt-1">You can now clone private repositories.</p>
                </div>
                <button
                    wire:click="goToLanding"
                    class="w-full px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-medium rounded-lg transition-colors duration-150 cursor-pointer"
                >
                    Continue
                </button>
            </div>
        @endif

        {{-- ERROR / EXPIRED / DENIED --}}
        @if (in_array($state, ['error', 'expired', 'denied']))
            <div class="space-y-5">
                <div class="flex items-center justify-center">
                    <div class="w-12 h-12 bg-red-600/20 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                </div>
                <div>
                    <p class="text-sm text-red-400">{{ $errorMessage }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <button
                        wire:click="retry"
                        class="w-full px-6 py-3 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 font-medium rounded-lg border border-zinc-700 transition-colors duration-150 cursor-pointer"
                    >
                        Try Again
                    </button>
                    <a href="/" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-400 transition-colors">
                        Back to Home
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
