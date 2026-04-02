<div class="flex h-full flex-col overflow-hidden">
    @if ($errorMessage)
        <div class="border-b border-red-800 bg-red-900/30 px-4 py-2 text-sm text-red-400">
            {{ $errorMessage }}
        </div>
    @endif

    @if ($activeTab)
        <div class="border-b border-[#3a3e4a] bg-[#31353f] px-3 py-1.5">
            <div class="flex items-center gap-1 overflow-x-auto">
                @foreach ($tabs as $tab)
                    <div
                        class="group flex shrink-0 items-center gap-1 rounded-t-md border-b-2 px-1.5 py-0.5 transition-colors {{ $activeTabId === $tab['id'] ? 'border-[#8e94a3] bg-[#3b404a] text-gray-100' : 'border-transparent text-gray-500 hover:bg-[#383d47] hover:text-gray-300' }}"
                    >
                        <button
                            wire:click="activateTab('{{ $tab['id'] }}')"
                            class="flex min-w-[9rem] max-w-[14rem] items-center gap-2 px-2 py-1 text-left cursor-pointer"
                            title="{{ $tab['path'] }}"
                        >
                            <svg class="h-3.5 w-3.5 shrink-0 {{ $activeTabId === $tab['id'] ? 'text-gray-300' : 'text-gray-600' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 3v12m0 0a3 3 0 103 3 3 3 0 00-3-3zm12-6a3 3 0 10-3-3 3 3 0 003 3zm0 0v2a2 2 0 01-2 2H9"/>
                            </svg>
                            <span class="truncate text-sm font-semibold">{{ $tab['repoName'] }}</span>
                        </button>

                        <button
                            wire:click="closeTab('{{ $tab['id'] }}')"
                            class="rounded p-1 text-gray-600 transition-colors hover:bg-[#2c3039] hover:text-gray-300 cursor-pointer"
                            title="Close tab"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                @endforeach

                <button
                    wire:click="openRepo"
                    class="ml-2 flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-[#4a4f5f] bg-[#363a44] text-gray-300 transition-colors hover:bg-[#404553] hover:text-gray-100 cursor-pointer"
                    title="Open repository"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="border-b border-[#3a3e4a] bg-[#353943] px-5 py-2.5">
            <div class="flex items-center justify-between gap-6">
                <div class="flex min-w-0 items-center gap-8">
                    <div class="min-w-0">
                        <div class="text-[10px] uppercase tracking-[0.18em] text-gray-500">Repository</div>
                        <div class="mt-1 flex items-center gap-2">
                            <div class="truncate text-[1.1rem] font-semibold leading-none text-gray-100" title="{{ $activeTab['path'] }}">
                                {{ $activeTab['repoName'] }}
                            </div>
                            <svg class="h-3.5 w-3.5 shrink-0 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>

                    <div class="min-w-0">
                        <div class="text-[10px] uppercase tracking-[0.18em] text-gray-500">Branch</div>
                        <div class="mt-1 flex items-center gap-2">
                            <div class="truncate text-[1.1rem] font-semibold leading-none text-gray-100">
                                {{ $activeTab['isDetached'] ? 'Detached HEAD' : ($activeTab['currentBranch'] ?? 'Unknown') }}
                            </div>
                            <svg class="h-3.5 w-3.5 shrink-0 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <button
                    wire:click="openRepo"
                    class="shrink-0 rounded-md border border-[#4a4f5f] bg-[#2c3038] px-3 py-1.5 text-[11px] font-medium uppercase tracking-[0.14em] text-gray-300 transition-colors hover:bg-[#373c47] hover:text-gray-100 cursor-pointer"
                >
                    Open Repository
                </button>
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
