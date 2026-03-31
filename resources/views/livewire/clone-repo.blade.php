<div class="flex items-center justify-center h-full">
    <div class="w-full max-w-lg px-6 space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-100">Clone Repository</h1>
                <p class="mt-1 text-sm text-zinc-500">Clone a Git repository to your machine.</p>
            </div>
            <a href="/" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-400 transition-colors">
                Back
            </a>
        </div>

        {{-- Error message --}}
        @if ($errorMessage)
            <div class="bg-red-900/30 border border-red-800 rounded-lg px-4 py-3 text-sm text-red-400">
                {{ $errorMessage }}
            </div>
        @endif

        {{-- IDLE: Input form --}}
        @if ($state === 'idle')
            <div class="space-y-5">
                {{-- Repo picker (if connected) --}}
                @if ($isGitHubConnected)
                    <div>
                        <label class="block text-xs font-medium text-zinc-500 mb-1.5">Your Repositories</label>

                        {{-- Loading state --}}
                        @if ($isLoadingRepos)
                            <div class="flex items-center gap-2 px-3 py-4 bg-zinc-800 border border-zinc-700 rounded-lg">
                                <svg class="animate-spin h-4 w-4 text-zinc-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span class="text-sm text-zinc-500">Loading repositories...</span>
                            </div>
                        @else
                            @php $filtered = array_slice($this->filteredRepos(), 0, 10); @endphp

                            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                {{-- Filter input --}}
                                <input
                                    type="text"
                                    wire:model.live="filterQuery"
                                    placeholder="Search your repos..."
                                    @focus="open = true"
                                    @input="open = true"
                                    class="w-full px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm text-zinc-200 placeholder-zinc-600 focus:outline-none focus:border-zinc-500 transition-colors"
                                />

                                {{-- Dropdown --}}
                                <div
                                    x-show="open"
                                    x-cloak
                                    class="absolute z-20 w-full mt-1 bg-zinc-800 border border-zinc-700 rounded-lg shadow-xl overflow-y-auto"
                                    style="max-height: 340px"
                                >
                                    @if (count($filtered) === 0)
                                        <div class="px-3 py-3 text-sm text-zinc-600 text-center">
                                            @if (count($allRepos) === 0)
                                                No repositories found.
                                            @else
                                                No repos match "{{ $filterQuery }}"
                                            @endif
                                        </div>
                                    @else
                                        @foreach ($filtered as $index => $repo)
                                            <button
                                                wire:click="selectRepo({{ $index }})"
                                                @click="open = false"
                                                class="w-full text-left px-3 py-2 hover:bg-zinc-700 transition-colors cursor-pointer first:rounded-t-lg last:rounded-b-lg border-b border-zinc-700/50 last:border-b-0"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <span class="text-sm text-zinc-200 font-medium truncate">{{ $repo['full_name'] }}</span>
                                                    @if ($repo['private'])
                                                        <span class="text-[10px] px-1.5 py-0.5 bg-amber-900/50 text-amber-400 rounded shrink-0">Private</span>
                                                    @endif
                                                    @if ($repo['language'])
                                                        <span class="text-[10px] text-zinc-600 shrink-0 ml-auto">{{ $repo['language'] }}</span>
                                                    @endif
                                                </div>
                                                @if ($repo['description'])
                                                    <p class="text-xs text-zinc-500 truncate mt-0.5">{{ $repo['description'] }}</p>
                                                @endif
                                            </button>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-3 text-xs text-zinc-600">
                        <div class="flex-1 h-px bg-zinc-800"></div>
                        <span>or enter URL directly</span>
                        <div class="flex-1 h-px bg-zinc-800"></div>
                    </div>
                @endif

                {{-- URL input --}}
                <div>
                    <label class="block text-xs font-medium text-zinc-500 mb-1.5">Repository URL</label>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            wire:model="cloneUrl"
                            placeholder="https://github.com/user/repo.git"
                            class="flex-1 px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm text-zinc-200 placeholder-zinc-600 focus:outline-none focus:border-zinc-500 transition-colors font-mono"
                        />
                        @if (empty($selectedRepoName) && !empty($cloneUrl))
                            <button
                                wire:click="setManualUrl"
                                class="px-3 py-2 bg-zinc-700 hover:bg-zinc-600 text-zinc-300 text-sm rounded-lg transition-colors cursor-pointer shrink-0"
                            >
                                Use
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Selected repo info --}}
                @if ($selectedRepoName)
                    <div class="bg-zinc-800/50 border border-zinc-700 rounded-lg px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-zinc-200">{{ $selectedRepoName }}</span>
                            @if ($selectedRepoPrivate)
                                <span class="text-[10px] px-1.5 py-0.5 bg-amber-900/50 text-amber-400 rounded">Private</span>
                            @endif
                        </div>
                        @if ($selectedRepoDescription)
                            <p class="text-xs text-zinc-500 mt-1">{{ $selectedRepoDescription }}</p>
                        @endif
                        <p class="text-xs text-zinc-600 font-mono mt-1 truncate">{{ $cloneUrl }}</p>
                    </div>
                @endif

                {{-- Destination --}}
                <div>
                    <label class="block text-xs font-medium text-zinc-500 mb-1.5">Clone to</label>
                    <div class="flex gap-2">
                        <div class="flex-1 px-3 py-2 bg-zinc-800 border border-zinc-700 rounded-lg text-sm text-zinc-400 font-mono truncate">
                            {{ $destinationPath ?: $destinationParent }}
                        </div>
                        <button
                            wire:click="chooseDestination"
                            class="px-3 py-2 bg-zinc-700 hover:bg-zinc-600 text-zinc-300 text-sm rounded-lg transition-colors cursor-pointer shrink-0"
                        >
                            Browse
                        </button>
                    </div>
                </div>

                {{-- Clone button --}}
                <button
                    wire:click="startClone"
                    @disabled(empty($cloneUrl) || empty($selectedRepoName))
                    class="w-full px-6 py-3 bg-emerald-600 hover:bg-emerald-500 disabled:bg-zinc-700 disabled:text-zinc-500 text-white font-medium rounded-lg transition-colors duration-150 cursor-pointer disabled:cursor-not-allowed"
                >
                    Clone Repository
                </button>
            </div>
        @endif

        {{-- CLONING: Progress --}}
        @if ($state === 'cloning')
            <div class="space-y-4 py-4">
                <div class="flex items-center gap-3 text-zinc-300">
                    <svg class="animate-spin h-5 w-5 text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span class="text-sm font-medium">Cloning {{ $selectedRepoName }}...</span>
                </div>
                @if ($cloneProgress)
                    <div class="px-3 py-2 bg-zinc-800 rounded-lg text-xs text-zinc-500 font-mono overflow-hidden">
                        {{ $cloneProgress }}
                    </div>
                @endif
                <p class="text-xs text-zinc-600">{{ $destinationPath }}</p>
            </div>
        @endif

        {{-- SUCCESS --}}
        @if ($state === 'success')
            <div class="space-y-5 text-center py-4">
                <div class="flex items-center justify-center">
                    <div class="w-12 h-12 bg-emerald-600/20 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>
                <div>
                    <p class="text-lg font-medium text-zinc-100">Repository Cloned</p>
                    <p class="text-sm text-zinc-500 mt-1 font-mono">{{ $destinationPath }}</p>
                </div>
                <div class="flex flex-col gap-2">
                    <button
                        wire:click="openClonedRepo"
                        class="w-full px-6 py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-medium rounded-lg transition-colors duration-150 cursor-pointer"
                    >
                        Open Repository
                    </button>
                    <a href="/" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-400 transition-colors">
                        Back to Home
                    </a>
                </div>
            </div>
        @endif

        {{-- ERROR --}}
        @if ($state === 'error')
            <div class="space-y-5 text-center py-4">
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
                        wire:click="reset_form"
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
