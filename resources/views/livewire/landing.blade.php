<div class="flex items-center justify-center h-full">
    <div class="text-center space-y-8 max-w-md w-full px-6">
        {{-- Logo / App name --}}
        <div>
            <h1 class="text-4xl font-bold text-gray-100 tracking-tight">Treehouse</h1>
            <p class="mt-2 text-sm text-purple-400/60">A desktop Git client</p>
        </div>

        {{-- Error message --}}
        @if ($errorMessage)
            <div class="bg-red-900/30 border border-red-800 rounded-lg px-4 py-3 text-sm text-red-400 flex items-center justify-between">
                <span>{{ $errorMessage }}</span>
                <button wire:click="$set('errorMessage', '')" class="text-red-500 hover:text-red-300 ml-3 shrink-0 cursor-pointer">&times;</button>
            </div>
        @endif

        {{-- Action buttons --}}
        <div class="flex flex-col gap-3">
            <button
                wire:click="openRepo"
                wire:loading.attr="disabled"
                class="w-full px-6 py-3 bg-violet-600 hover:bg-violet-500 text-white font-medium rounded-lg transition-colors duration-150 cursor-pointer disabled:opacity-50 glow-violet"
            >
                <span wire:loading.remove wire:target="openRepo">Open Repository</span>
                <span wire:loading wire:target="openRepo">Opening...</span>
            </button>
            <a
                href="/clone"
                wire:navigate
                class="w-full px-6 py-3 bg-[#1a1a2e] hover:bg-[#141420] text-gray-200 font-medium rounded-lg border border-[#2a2a42] transition-colors duration-150 cursor-pointer text-center block"
            >
                Clone Repository
            </a>
        </div>

        {{-- Recent repos --}}
        <div class="text-left">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-purple-400/50 mb-3">Recent Repositories</h2>

            @if (count($recentRepos) > 0)
                <div class="space-y-1">
                    @foreach ($recentRepos as $repo)
                        <div
                            class="group flex items-center justify-between px-3 py-2 rounded-lg hover:bg-[#1a1a2e] transition-colors cursor-pointer"
                            wire:click="openRepoByPath('{{ $repo['path'] }}')"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-medium text-gray-200 truncate">{{ $repo['name'] }}</div>
                                <div class="text-xs text-gray-600 truncate">
                                    {{ $repo['path'] }}
                                    @if ($repo['branch'])
                                        <span class="text-gray-500 ml-1">{{ $repo['branch'] }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2 ml-3 shrink-0">
                                <span class="text-xs text-gray-600">{{ $repo['last_opened_at'] }}</span>
                                <button
                                    wire:click.stop="removeRecent('{{ $repo['path'] }}')"
                                    class="opacity-0 group-hover:opacity-100 text-gray-600 hover:text-gray-400 transition-all cursor-pointer p-1"
                                    title="Remove from recent"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-4 text-center">
                    <div class="text-sm text-gray-600">No recent repositories</div>
                    <div class="text-xs text-gray-700 mt-1">Open or clone a repository to get started</div>
                </div>
            @endif
        </div>

        {{-- GitHub connection status --}}
        <div class="pt-4 border-t border-[#1e1e32]">
            @if ($isGitHubConnected)
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm text-gray-400">
                        <div class="w-2 h-2 rounded-full bg-violet-500 shadow-[0_0_6px_rgba(139,92,246,0.5)]"></div>
                        <span>
                            Connected as
                            <span class="text-gray-300 font-medium">{{ $gitHubUser['login'] ?? 'unknown' }}</span>
                        </span>
                    </div>
                    <button
                        wire:click="disconnectGitHub"
                        wire:confirm="Disconnect from GitHub?"
                        class="text-xs text-gray-600 hover:text-gray-400 transition-colors cursor-pointer"
                    >
                        Disconnect
                    </button>
                </div>
            @else
                <a
                    href="/github/login"
                    wire:navigate
                    class="flex items-center justify-center gap-2 text-sm text-gray-500 hover:text-gray-300 transition-colors"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/>
                    </svg>
                    Connect to GitHub
                </a>
            @endif
        </div>
    </div>
</div>
