<div class="flex h-full flex-col overflow-hidden" x-data="{ branchDropdownOpen: false }">
    @if ($errorMessage)
        <div class="border-b border-red-800 bg-red-900/30 px-4 py-2 text-sm text-red-400">
            {{ $errorMessage }}
        </div>
    @endif

    @if ($activeTab)
        @php
            $localBranches = $activeTab['localBranches'] ?? [];
            $recentBranches = array_values(array_intersect($activeTab['recentBranches'] ?? [], $localBranches));
            $otherBranches = array_values(array_diff($localBranches, $recentBranches));
        @endphp

        <div class="border-b border-[#3a3e4a] bg-[#31353f] px-2 py-1">
            <div class="flex items-center gap-0.5 overflow-x-auto">
                @foreach ($tabs as $tab)
                    <div
                        class="group flex shrink-0 items-center gap-0.5 rounded-t-md border-b-2 px-1 py-0 transition-colors {{ $activeTabId === $tab['id'] ? 'border-[#8e94a3] bg-[#3b404a] text-gray-100' : 'border-transparent text-gray-500 hover:bg-[#383d47] hover:text-gray-300' }}"
                    >
                        <button
                            wire:click="activateTab(@js($tab['id']))"
                            class="flex min-w-[7.25rem] max-w-[10.5rem] items-center gap-1.5 px-1.5 py-0.5 text-left cursor-pointer"
                            title="{{ $tab['path'] }}"
                        >
                            <svg class="h-3 w-3 shrink-0 {{ $activeTabId === $tab['id'] ? 'text-gray-300' : 'text-gray-600' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 3v12m0 0a3 3 0 103 3 3 3 0 00-3-3zm12-6a3 3 0 10-3-3 3 3 0 003 3zm0 0v2a2 2 0 01-2 2H9"/>
                            </svg>
                            <span class="truncate text-xs font-semibold">{{ $tab['repoName'] }}</span>
                        </button>

                        <button
                            wire:click="closeTab(@js($tab['id']))"
                            class="rounded p-0.5 text-gray-600 transition-colors hover:bg-[#2c3039] hover:text-gray-300 cursor-pointer"
                            title="Close tab"
                        >
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                @endforeach

                <button
                    wire:click="openRepo"
                    class="ml-1.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-[#4a4f5f] bg-[#363a44] text-gray-300 transition-colors hover:bg-[#404553] hover:text-gray-100 cursor-pointer"
                    title="Open repository"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="border-b border-[#3a3e4a] bg-[#353943] px-4 py-2">
            <div class="flex items-center gap-6">
                <div class="flex min-w-0 items-center gap-6">
                    <div class="min-w-0">
                        <div class="text-[10px] uppercase tracking-[0.18em] text-gray-500">Repository</div>
                        <div class="mt-1 flex items-center gap-2">
                            <div class="truncate text-[1.05rem] font-semibold leading-none text-gray-100" title="{{ $activeTab['path'] }}">
                                {{ $activeTab['repoName'] }}
                            </div>
                            <svg class="h-3.5 w-3.5 shrink-0 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>

                    <div class="relative min-w-0">
                        <div class="text-[10px] uppercase tracking-[0.18em] text-gray-500">Branch</div>
                        <div class="mt-1 flex items-center gap-2">
                            <button
                                type="button"
                                x-on:click="branchDropdownOpen = ! branchDropdownOpen"
                                x-on:keydown.escape.window="branchDropdownOpen = false"
                                x-bind:aria-expanded="branchDropdownOpen"
                                aria-haspopup="menu"
                                class="flex max-w-[18rem] items-center gap-2 rounded px-1 py-0.5 -ml-1 transition-colors hover:bg-[#2c3039] cursor-pointer"
                                title="Switch branch"
                            >
                                <span class="truncate text-[1.05rem] font-semibold leading-none text-gray-100">
                                    {{ $activeTab['isDetached'] ? 'Detached HEAD' : ($activeTab['currentBranch'] ?? 'Unknown') }}
                                </span>
                                <svg
                                    class="h-3.5 w-3.5 shrink-0 text-gray-500 transition-transform"
                                    x-bind:class="branchDropdownOpen ? 'rotate-180' : ''"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <button
                                type="button"
                                x-on:click="window.dispatchEvent(new CustomEvent('workspace-repo-action', { detail: { action: 'target' } }))"
                                class="flex shrink-0 items-center gap-1 rounded-md border border-[#4a4f5f] bg-[#2c3038] px-2 py-1 text-[10px] font-medium text-gray-300 transition-colors hover:bg-[#404553] hover:text-gray-100 cursor-pointer"
                                title="Show the current checkout in the graph"
                            >
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="7"/>
                                    <circle cx="12" cy="12" r="2"/>
                                    <path stroke-linecap="round" d="M12 2v3m0 14v3M2 12h3m14 0h3"/>
                                </svg>
                                <span>Target</span>
                            </button>
                        </div>

                        <div
                            x-show="branchDropdownOpen"
                            x-on:click.outside="branchDropdownOpen = false"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-1"
                            x-cloak
                            role="menu"
                            class="absolute left-0 top-full z-50 mt-2 w-72 overflow-hidden rounded-lg border border-[#4a4f5f] bg-[#252932] shadow-2xl"
                        >
                            <div class="max-h-80 overflow-y-auto py-1.5">
                                @if ($recentBranches !== [])
                                    <div class="px-3 pb-1 pt-1 text-[9px] font-semibold uppercase tracking-[0.18em] text-gray-600">Recent</div>
                                    @foreach ($recentBranches as $branch)
                                        <button
                                            type="button"
                                            @disabled($branch === $activeTab['currentBranch'])
                                            x-on:click="
                                                branchDropdownOpen = false;
                                                window.dispatchEvent(new CustomEvent('workspace-repo-action', { detail: { action: 'checkout-branch', branch: @js($branch) } }));
                                            "
                                            role="menuitem"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs transition-colors {{ $branch === $activeTab['currentBranch'] ? 'text-gray-200' : 'text-gray-400 hover:bg-[#303541] hover:text-gray-100 cursor-pointer' }}"
                                            title="Switch to {{ $branch }}"
                                        >
                                            <span class="h-2 w-2 shrink-0 rounded-full {{ $branch === $activeTab['currentBranch'] ? 'bg-violet-500' : 'bg-transparent' }}"></span>
                                            <span class="min-w-0 flex-1 truncate">{{ $branch }}</span>
                                            @if ($branch === $activeTab['currentBranch'])
                                                <span class="text-[9px] uppercase tracking-[0.14em] text-violet-400">Current</span>
                                            @endif
                                        </button>
                                    @endforeach
                                @endif

                                @if ($otherBranches !== [])
                                    <div class="{{ $recentBranches !== [] ? 'mt-1 border-t border-[#3a3e4a]' : '' }} px-3 pb-1 pt-2 text-[9px] font-semibold uppercase tracking-[0.18em] text-gray-600">Branches</div>
                                    @foreach ($otherBranches as $branch)
                                        <button
                                            type="button"
                                            x-on:click="
                                                branchDropdownOpen = false;
                                                window.dispatchEvent(new CustomEvent('workspace-repo-action', { detail: { action: 'checkout-branch', branch: @js($branch) } }));
                                            "
                                            role="menuitem"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs text-gray-400 transition-colors hover:bg-[#303541] hover:text-gray-100 cursor-pointer"
                                            title="Switch to {{ $branch }}"
                                        >
                                            <span class="h-2 w-2 shrink-0 rounded-full bg-transparent"></span>
                                            <span class="min-w-0 flex-1 truncate">{{ $branch }}</span>
                                        </button>
                                    @endforeach
                                @endif

                                @if ($localBranches === [])
                                    <div class="px-3 py-3 text-xs italic text-gray-600">No local branches available</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ml-auto flex shrink-0 items-center gap-1.5">
                    <button
                        x-on:click="window.dispatchEvent(new CustomEvent('workspace-repo-action', { detail: { action: 'refresh' } }))"
                        class="flex items-center gap-1 rounded-md px-2 py-1 text-[10px] font-medium text-gray-300 transition-colors hover:bg-[#2c3039] hover:text-gray-100 cursor-pointer"
                        title="Refresh (Cmd+R)"
                    >
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span>Refresh</span>
                    </button>

                    <button
                        x-on:click="window.dispatchEvent(new CustomEvent('workspace-repo-action', { detail: { action: 'fetch' } }))"
                        class="flex items-center gap-1 rounded-md px-2 py-1 text-[10px] font-medium text-gray-300 transition-colors hover:bg-[#2c3039] hover:text-gray-100 cursor-pointer"
                        title="Fetch (Cmd+Shift+F)"
                    >
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        <span>Fetch</span>
                    </button>

                    <button
                        x-on:click="window.dispatchEvent(new CustomEvent('workspace-repo-action', { detail: { action: 'pull' } }))"
                        class="flex items-center gap-1 rounded-md px-2 py-1 text-[10px] font-medium text-gray-300 transition-colors hover:bg-[#2c3039] hover:text-gray-100 cursor-pointer"
                        title="Pull (Cmd+Shift+L)"
                    >
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                        <span>Pull</span>
                    </button>

                    <button
                        x-on:click="window.dispatchEvent(new CustomEvent('workspace-repo-action', { detail: { action: 'push' } }))"
                        class="flex items-center gap-1 rounded-md px-2 py-1 text-[10px] font-medium text-gray-300 transition-colors hover:bg-[#2c3039] hover:text-gray-100 cursor-pointer"
                        title="Push (Cmd+Shift+P)"
                    >
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                        </svg>
                        <span>Push</span>
                    </button>

                    <a
                        href="/"
                        wire:navigate
                        class="ml-2 rounded-md border border-[#4a4f5f] bg-[#2c3038] px-2.5 py-1 text-[10px] font-medium uppercase tracking-[0.14em] text-gray-300 transition-colors hover:bg-[#373c47] hover:text-gray-100 cursor-pointer"
                    >
                        Repository menu
                    </a>
                </div>
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
