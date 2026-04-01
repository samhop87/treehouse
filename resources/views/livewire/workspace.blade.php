<div class="flex h-full flex-col overflow-hidden">
    @if ($errorMessage)
        <div class="border-b border-red-800 bg-red-900/30 px-4 py-2 text-sm text-red-400">
            {{ $errorMessage }}
        </div>
    @endif

    @if ($activeTab)
        <div class="border-b border-[#1e1e32] bg-[#0e0e18] px-4 py-3">
            <div class="flex items-center justify-between gap-4">
                <div class="grid min-w-0 grid-cols-2 gap-4">
                    <div class="min-w-0">
                        <div class="text-[10px] uppercase tracking-[0.2em] text-gray-600">Repository</div>
                        <div class="truncate text-lg font-semibold text-gray-100" title="{{ $activeTab['path'] }}">
                            {{ $activeTab['repoName'] }}
                        </div>
                    </div>

                    <div class="min-w-0">
                        <div class="text-[10px] uppercase tracking-[0.2em] text-gray-600">Branch</div>
                        <div class="truncate text-lg font-semibold text-gray-100">
                            {{ $activeTab['isDetached'] ? 'Detached HEAD' : ($activeTab['currentBranch'] ?? 'Unknown') }}
                        </div>
                    </div>
                </div>

                <button
                    wire:click="openRepo"
                    class="shrink-0 rounded-md border border-[#2a2a42] bg-[#1a1a2e] px-3 py-2 text-xs font-medium text-gray-300 transition-colors hover:bg-[#202035] hover:text-gray-100 cursor-pointer"
                >
                    Open Repository
                </button>
            </div>
        </div>

        <div class="border-b border-[#1e1e32] bg-[#06060c] px-2 py-1.5">
            <div class="flex items-center gap-1 overflow-x-auto">
                @foreach ($tabs as $tab)
                    <div
                        class="group flex shrink-0 items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors {{ $activeTabId === $tab['id'] ? 'border-violet-700/50 bg-violet-900/20 text-gray-100' : 'border-transparent bg-[#11111b] text-gray-500 hover:bg-[#1a1a2e] hover:text-gray-300' }}"
                    >
                        <button
                            wire:click="activateTab('{{ $tab['id'] }}')"
                            class="min-w-0 cursor-pointer text-left"
                            title="{{ $tab['path'] }}"
                        >
                            <span class="block truncate font-medium">{{ $tab['repoName'] }}</span>
                            <span class="block truncate text-[10px] uppercase tracking-[0.2em] text-gray-600">
                                {{ $tab['isDetached'] ? 'Detached HEAD' : ($tab['currentBranch'] ?? 'No branch') }}
                            </span>
                        </button>

                        <button
                            wire:click="closeTab('{{ $tab['id'] }}')"
                            class="rounded p-1 text-gray-600 transition-colors hover:bg-[#1f1f31] hover:text-gray-300 cursor-pointer"
                            title="Close tab"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex-1 min-h-0 overflow-hidden">
            @foreach ($tabs as $tab)
                <div class="{{ $activeTabId === $tab['id'] ? 'h-full' : 'hidden h-full' }}">
                    <livewire:repo-view
                        :path="$tab['path']"
                        :tab-id="$tab['id']"
                        :is-active="$activeTabId === $tab['id']"
                        :key="$tab['id']"
                    />
                </div>
            @endforeach
        </div>
    @endif

    <x-slot:titleBar>
        Treehouse
        @if ($activeTab)
            <span class="ml-1 text-gray-600">/ {{ $activeTab['repoName'] }}</span>
            <span class="ml-1 text-gray-700">
                {{ $activeTab['isDetached'] ? '(Detached HEAD)' : '/ ' . ($activeTab['currentBranch'] ?? 'Unknown') }}
            </span>
        @endif
    </x-slot:titleBar>
</div>
