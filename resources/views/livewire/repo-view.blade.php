<div
    class="flex h-full"
    x-data="{ sidebarTab: 'branches' }"
    x-init="
        let lastRefresh = Date.now();
        window.addEventListener('focus', () => {
            if (Date.now() - lastRefresh > 3000) {
                lastRefresh = Date.now();
                $wire.refresh();
            }
        });
    "
    @keydown.window="
        if ($event.metaKey && !$event.shiftKey && $event.key === 'r') {
            $event.preventDefault();
            $wire.refresh();
        }
        if ($event.metaKey && $event.shiftKey && $event.key === 'p') {
            $event.preventDefault();
            $wire.pushRemote();
        }
        if ($event.metaKey && $event.shiftKey && $event.key === 'f') {
            $event.preventDefault();
            $wire.fetchRemote();
        }
        if ($event.metaKey && $event.shiftKey && $event.key === 'l') {
            $event.preventDefault();
            $wire.pullRemote();
        }
        if ($event.metaKey && $event.key === 'Enter') {
            $event.preventDefault();
            $wire.commit();
        }
        if ($event.key === 'Escape') {
            $wire.clearFileSelection();
            $wire.closeCreateBranch();
            $wire.closeCreateTag();
            $wire.closeCreateStash();
            $wire.closeMerge();
        }
    "
>

    {{-- ═══ LEFT SIDEBAR ═══ --}}
    <aside class="w-56 shrink-0 bg-zinc-950 border-r border-zinc-800 flex flex-col overflow-hidden">

        {{-- Repo name + back button --}}
        <div class="flex items-center gap-2 px-3 py-2 border-b border-zinc-800">
            <button
                wire:click="goHome"
                class="text-zinc-500 hover:text-zinc-300 transition-colors shrink-0 cursor-pointer"
                title="Back to home"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <span class="text-sm font-medium text-zinc-300 truncate" title="{{ $path }}">{{ $repoName }}</span>
        </div>

        {{-- Branch/status header --}}
        <div class="px-3 py-2 border-b border-zinc-800">
            @if ($isDetached)
                <div class="text-xs text-amber-400 font-medium">Detached HEAD</div>
                <div
                    class="text-xs text-zinc-500 truncate cursor-pointer hover:text-zinc-300 transition-colors"
                    x-on:click="navigator.clipboard.writeText('{{ $status['headHash'] ?? '' }}'); $dispatch('toast', { message: 'Hash copied', type: 'success' })"
                    title="Click to copy hash"
                >{{ $status['headHash'] ?? '' }}</div>
            @else
                <div
                    class="text-sm text-zinc-200 font-medium truncate cursor-pointer hover:text-white transition-colors"
                    x-on:click="navigator.clipboard.writeText('{{ $currentBranch }}'); $dispatch('toast', { message: 'Branch name copied', type: 'success' })"
                    title="Click to copy branch name"
                >{{ $currentBranch }}</div>
                @if ($upstream)
                    <div class="flex items-center gap-1 mt-0.5">
                        <span class="text-xs text-zinc-500 truncate">{{ $upstream }}</span>
                        @if ($ahead > 0)
                            <span class="text-xs text-emerald-400">+{{ $ahead }}</span>
                        @endif
                        @if ($behind > 0)
                            <span class="text-xs text-amber-400">-{{ $behind }}</span>
                        @endif
                    </div>
                @endif
            @endif
        </div>

        {{-- Sidebar tabs --}}
        <div class="flex border-b border-zinc-800">
            <button
                @click="sidebarTab = 'branches'"
                :class="sidebarTab === 'branches' ? 'text-zinc-200 border-b-2 border-emerald-500' : 'text-zinc-500 hover:text-zinc-400'"
                class="flex-1 text-xs font-medium py-2 px-1 text-center transition-colors cursor-pointer"
            >
                Branches
            </button>
            <button
                @click="sidebarTab = 'tags'"
                :class="sidebarTab === 'tags' ? 'text-zinc-200 border-b-2 border-emerald-500' : 'text-zinc-500 hover:text-zinc-400'"
                class="flex-1 text-xs font-medium py-2 px-1 text-center transition-colors cursor-pointer"
            >
                Tags
                @if (count($tags) > 0)
                    <span class="text-zinc-600 ml-0.5">{{ count($tags) }}</span>
                @endif
            </button>
            <button
                @click="sidebarTab = 'stashes'"
                :class="sidebarTab === 'stashes' ? 'text-zinc-200 border-b-2 border-emerald-500' : 'text-zinc-500 hover:text-zinc-400'"
                class="flex-1 text-xs font-medium py-2 px-1 text-center transition-colors cursor-pointer"
            >
                Stashes
                @if (count($stashes) > 0)
                    <span class="text-zinc-600 ml-0.5">{{ count($stashes) }}</span>
                @endif
            </button>
        </div>

        {{-- Sidebar content --}}
        <div class="flex-1 overflow-y-auto">

            {{-- BRANCHES TAB --}}
            <div x-show="sidebarTab === 'branches'" x-cloak class="py-1">
                {{-- Create branch button --}}
                <div class="px-3 py-1">
                    @if ($showCreateBranch)
                        <div class="bg-zinc-800 rounded p-2 mb-2">
                            <input
                                wire:model="newBranchName"
                                wire:keydown.enter="createBranch"
                                wire:keydown.escape="closeCreateBranch"
                                type="text"
                                placeholder="Branch name..."
                                class="w-full bg-zinc-900 border border-zinc-700 rounded px-2 py-1 text-xs text-zinc-200 placeholder:text-zinc-600 focus:outline-none focus:border-emerald-600"
                                autofocus
                            >
                            <input
                                wire:model="newBranchStartPoint"
                                wire:keydown.enter="createBranch"
                                type="text"
                                placeholder="Start point (optional, default: HEAD)"
                                class="w-full mt-1 bg-zinc-900 border border-zinc-700 rounded px-2 py-1 text-xs text-zinc-200 placeholder:text-zinc-600 focus:outline-none focus:border-emerald-600"
                            >
                            <div class="flex gap-1 mt-1.5">
                                <button
                                    wire:click="createBranch"
                                    class="flex-1 px-2 py-1 bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-medium rounded transition-colors cursor-pointer"
                                >Create & Switch</button>
                                <button
                                    wire:click="closeCreateBranch"
                                    class="px-2 py-1 bg-zinc-700 hover:bg-zinc-600 text-zinc-300 text-[10px] rounded transition-colors cursor-pointer"
                                >Cancel</button>
                            </div>
                        </div>
                    @else
                        <button
                            wire:click="openCreateBranch"
                            class="flex items-center gap-1 w-full px-2 py-1 text-xs text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800/50 rounded transition-colors cursor-pointer mb-1"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            New branch
                        </button>
                    @endif
                </div>

                {{-- Merge confirm modal --}}
                @if ($showMergeConfirm)
                    <div class="mx-3 mb-2 bg-zinc-800 rounded p-2 border border-zinc-700">
                        <div class="text-xs text-zinc-300 mb-1.5">
                            Merge <span class="font-medium text-zinc-100">{{ $mergeBranchName }}</span> into <span class="font-medium text-zinc-100">{{ $currentBranch }}</span>?
                        </div>
                        <div class="flex gap-1">
                            <button
                                wire:click="mergeBranch"
                                class="flex-1 px-2 py-1 bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-medium rounded transition-colors cursor-pointer"
                            >Merge</button>
                            <button
                                wire:click="closeMerge"
                                class="px-2 py-1 bg-zinc-700 hover:bg-zinc-600 text-zinc-300 text-[10px] rounded transition-colors cursor-pointer"
                            >Cancel</button>
                        </div>
                    </div>
                @endif

                {{-- Merge abort (shown when conflicts exist) --}}
                @if ($status && $status['hasConflicts'])
                    <div class="mx-3 mb-2 bg-red-900/20 border border-red-800/50 rounded p-2">
                        <div class="text-xs text-red-400 mb-1">Merge conflicts detected</div>
                        <button
                            wire:click="mergeAbort"
                            wire:confirm="Abort the current merge? All merge changes will be lost."
                            class="w-full px-2 py-1 bg-red-700 hover:bg-red-600 text-white text-[10px] font-medium rounded transition-colors cursor-pointer"
                        >Abort Merge</button>
                    </div>
                @endif

                {{-- Local branches --}}
                <div class="px-3 py-1">
                    <div class="text-[10px] uppercase tracking-wider text-zinc-600 font-semibold mb-1">Local</div>
                    @foreach ($localBranches as $branch)
                        <div class="group flex items-center gap-1.5 w-full px-2 py-1 rounded text-left text-xs transition-colors
                            {{ $branch['isCurrent'] ? 'bg-zinc-800 text-zinc-100' : 'text-zinc-400 hover:bg-zinc-800/50 hover:text-zinc-300' }}">
                            {{-- Checkout on click (if not current) --}}
                            <button
                                wire:click="checkoutBranch('{{ $branch['name'] }}')"
                                class="flex items-center gap-1.5 flex-1 min-w-0 cursor-pointer"
                                @if($branch['isCurrent']) disabled @endif
                            >
                                @if ($branch['isCurrent'])
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                @else
                                    <span class="w-1.5 h-1.5 shrink-0"></span>
                                @endif
                                <span class="truncate">{{ $branch['name'] }}</span>
                            </button>
                            @if ($branch['ahead'])
                                <span class="text-emerald-500 text-[10px]">+{{ $branch['ahead'] }}</span>
                            @endif
                            @if ($branch['behind'])
                                <span class="text-amber-500 text-[10px]">-{{ $branch['behind'] }}</span>
                            @endif
                            {{-- Actions — shown on hover --}}
                            <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 shrink-0">
                                <button
                                    x-on:click.stop="navigator.clipboard.writeText('{{ $branch['name'] }}'); $dispatch('toast', { message: 'Branch name copied', type: 'success' })"
                                    class="text-zinc-600 hover:text-zinc-300 cursor-pointer p-0.5"
                                    title="Copy branch name"
                                >
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </button>
                                @if (! $branch['isCurrent'])
                                    <button
                                        wire:click="openMerge('{{ $branch['name'] }}')"
                                        class="text-zinc-600 hover:text-emerald-400 cursor-pointer p-0.5"
                                        title="Merge into {{ $currentBranch }}"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                        </svg>
                                    </button>
                                    <button
                                        wire:click="deleteBranch('{{ $branch['name'] }}')"
                                        wire:confirm="Delete branch '{{ $branch['name'] }}'?"
                                        class="text-zinc-600 hover:text-red-400 cursor-pointer p-0.5"
                                        title="Delete branch"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Remote branches --}}
                @if (count($remoteBranches) > 0)
                    <div class="px-3 py-1 mt-2">
                        <div class="text-[10px] uppercase tracking-wider text-zinc-600 font-semibold mb-1">Remote</div>
                        @foreach ($remoteBranches as $branch)
                            <button
                                wire:click="checkoutBranch('{{ $branch['name'] }}')"
                                class="flex items-center gap-1.5 w-full px-2 py-1 text-xs text-zinc-500 hover:bg-zinc-800/50 hover:text-zinc-400 rounded transition-colors cursor-pointer"
                                title="Checkout remote branch (creates local tracking branch)"
                            >
                                <span class="w-1.5 h-1.5 shrink-0"></span>
                                <span class="truncate">{{ $branch['name'] }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- TAGS TAB --}}
            <div x-show="sidebarTab === 'tags'" x-cloak class="py-1">
                {{-- Create tag button / form --}}
                <div class="px-3 py-1">
                    @if ($showCreateTag)
                        <div class="bg-zinc-800 rounded p-2 mb-2">
                            <input
                                wire:model="newTagName"
                                wire:keydown.escape="closeCreateTag"
                                type="text"
                                placeholder="Tag name..."
                                class="w-full bg-zinc-900 border border-zinc-700 rounded px-2 py-1 text-xs text-zinc-200 placeholder:text-zinc-600 focus:outline-none focus:border-emerald-600"
                                autofocus
                            >
                            <input
                                wire:model="newTagRef"
                                type="text"
                                placeholder="Commit (optional, default: HEAD)"
                                class="w-full mt-1 bg-zinc-900 border border-zinc-700 rounded px-2 py-1 text-xs text-zinc-200 placeholder:text-zinc-600 focus:outline-none focus:border-emerald-600"
                            >
                            <label class="flex items-center gap-1.5 mt-1.5 text-xs text-zinc-400 cursor-pointer">
                                <input
                                    wire:model.live="newTagAnnotated"
                                    type="checkbox"
                                    class="rounded border-zinc-600 bg-zinc-900 text-emerald-500 focus:ring-emerald-600 focus:ring-offset-0 w-3 h-3"
                                >
                                Annotated tag
                            </label>
                            @if ($newTagAnnotated)
                                <textarea
                                    wire:model="newTagMessage"
                                    placeholder="Tag message..."
                                    rows="2"
                                    class="w-full mt-1 bg-zinc-900 border border-zinc-700 rounded px-2 py-1 text-xs text-zinc-200 placeholder:text-zinc-600 resize-none focus:outline-none focus:border-emerald-600"
                                ></textarea>
                            @endif
                            <div class="flex gap-1 mt-1.5">
                                <button
                                    wire:click="createTag"
                                    class="flex-1 px-2 py-1 bg-amber-600 hover:bg-amber-500 text-white text-[10px] font-medium rounded transition-colors cursor-pointer"
                                >Create Tag</button>
                                <button
                                    wire:click="closeCreateTag"
                                    class="px-2 py-1 bg-zinc-700 hover:bg-zinc-600 text-zinc-300 text-[10px] rounded transition-colors cursor-pointer"
                                >Cancel</button>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-1">
                            <button
                                wire:click="openCreateTag"
                                class="flex items-center gap-1 flex-1 px-2 py-1 text-xs text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800/50 rounded transition-colors cursor-pointer"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                                New tag
                            </button>
                            @if (count($tags) > 0)
                                <button
                                    wire:click="pushAllTags"
                                    wire:confirm="Push all tags to origin?"
                                    class="px-2 py-1 text-[10px] text-zinc-600 hover:text-zinc-400 hover:bg-zinc-800/50 rounded transition-colors cursor-pointer"
                                    title="Push all tags to origin"
                                >
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                    </svg>
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

                @if (count($tags) > 0)
                    @foreach ($tags as $tag)
                        <div class="group flex items-center gap-1.5 px-3 py-1.5 text-xs text-zinc-400 hover:bg-zinc-800/50 transition-colors">
                            <svg class="w-3 h-3 shrink-0 {{ $tag['isAnnotated'] ? 'text-amber-500' : 'text-zinc-600' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                            <span class="truncate flex-1" title="{{ $tag['isAnnotated'] ? 'Annotated: ' . $tag['message'] : 'Lightweight' }}">{{ $tag['name'] }}</span>
                            <span class="text-zinc-700 text-[10px] font-mono shrink-0">{{ substr($tag['commitHash'], 0, 7) }}</span>
                            {{-- Hover actions: copy, push + delete --}}
                            <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 shrink-0">
                                <button
                                    x-on:click="navigator.clipboard.writeText('{{ $tag['name'] }}'); $dispatch('toast', { message: 'Tag name copied', type: 'success' })"
                                    class="text-zinc-600 hover:text-zinc-300 cursor-pointer p-0.5"
                                    title="Copy tag name"
                                >
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </button>
                                    wire:click="pushTag('{{ $tag['name'] }}')"
                                    class="text-zinc-600 hover:text-emerald-400 cursor-pointer p-0.5"
                                    title="Push tag to origin"
                                >
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                    </svg>
                                </button>
                                <button
                                    wire:click="deleteTag('{{ $tag['name'] }}')"
                                    wire:confirm="Delete tag '{{ $tag['name'] }}'?"
                                    class="text-zinc-600 hover:text-red-400 cursor-pointer p-0.5"
                                    title="Delete tag"
                                >
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="px-3 py-4 text-xs text-zinc-600 italic text-center">No tags</div>
                @endif
            </div>

            {{-- STASHES TAB --}}
            <div x-show="sidebarTab === 'stashes'" x-cloak class="py-1">
                {{-- Create stash button / form --}}
                <div class="px-3 py-1">
                    @if ($showCreateStash)
                        <div class="bg-zinc-800 rounded p-2 mb-2">
                            <input
                                wire:model="newStashMessage"
                                wire:keydown.enter="createStash"
                                wire:keydown.escape="closeCreateStash"
                                type="text"
                                placeholder="Stash message (optional)..."
                                class="w-full bg-zinc-900 border border-zinc-700 rounded px-2 py-1 text-xs text-zinc-200 placeholder:text-zinc-600 focus:outline-none focus:border-emerald-600"
                                autofocus
                            >
                            <div class="flex gap-1 mt-1.5">
                                <button
                                    wire:click="createStash"
                                    class="flex-1 px-2 py-1 bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-medium rounded transition-colors cursor-pointer"
                                >Stash Changes</button>
                                <button
                                    wire:click="closeCreateStash"
                                    class="px-2 py-1 bg-zinc-700 hover:bg-zinc-600 text-zinc-300 text-[10px] rounded transition-colors cursor-pointer"
                                >Cancel</button>
                            </div>
                        </div>
                    @else
                        <button
                            wire:click="openCreateStash"
                            class="flex items-center gap-1 w-full px-2 py-1 text-xs text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800/50 rounded transition-colors cursor-pointer"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Stash changes
                        </button>
                    @endif
                </div>

                @if (count($stashes) > 0)
                    @foreach ($stashes as $stash)
                        <div class="group px-3 py-1.5 text-xs text-zinc-400 hover:bg-zinc-800/50 transition-colors">
                            <div class="flex items-center gap-1.5">
                                <span class="text-zinc-600 font-mono">{{ $stash['ref'] }}</span>
                                {{-- Hover actions: apply, pop, drop --}}
                                <div class="flex items-center gap-0.5 ml-auto opacity-0 group-hover:opacity-100 shrink-0">
                                    <button
                                        wire:click="stashApply('{{ $stash['ref'] }}')"
                                        class="text-zinc-600 hover:text-emerald-400 cursor-pointer px-1 py-0.5 rounded hover:bg-zinc-800 text-[10px]"
                                        title="Apply (keep in stash list)"
                                    >Apply</button>
                                    <button
                                        wire:click="stashPop('{{ $stash['ref'] }}')"
                                        class="text-zinc-600 hover:text-amber-400 cursor-pointer px-1 py-0.5 rounded hover:bg-zinc-800 text-[10px]"
                                        title="Pop (apply and remove)"
                                    >Pop</button>
                                    <button
                                        wire:click="stashDrop('{{ $stash['ref'] }}')"
                                        wire:confirm="Drop {{ $stash['ref'] }}? This cannot be undone."
                                        class="text-zinc-600 hover:text-red-400 cursor-pointer px-1 py-0.5 rounded hover:bg-zinc-800 text-[10px]"
                                        title="Drop (delete without applying)"
                                    >Drop</button>
                                </div>
                            </div>
                            <div class="truncate mt-0.5 text-zinc-500">{{ $stash['message'] }}</div>
                        </div>
                    @endforeach
                @else
                    <div class="px-3 py-4 text-xs text-zinc-600 italic text-center">No stashes</div>
                @endif
            </div>
        </div>
    </aside>

    {{-- ═══ MAIN CONTENT ═══ --}}
    <div class="flex-1 flex flex-col overflow-hidden">

        {{-- Error banner --}}
        @if ($errorMessage)
            <div class="bg-red-900/30 border-b border-red-800 px-4 py-2 text-sm text-red-400 flex items-center justify-between">
                <span>{{ $errorMessage }}</span>
                <button wire:click="$set('errorMessage', '')" class="text-red-500 hover:text-red-400 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        {{-- Top toolbar --}}
        <div class="flex items-center gap-1 px-3 py-1.5 bg-zinc-900 border-b border-zinc-800">
            <button
                wire:click="refresh"
                wire:loading.attr="disabled"
                class="flex items-center gap-1 px-2 py-1 text-xs text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800 rounded transition-colors cursor-pointer"
                title="Refresh (Cmd+R)"
            >
                <svg class="w-3.5 h-3.5" wire:loading.class="animate-spin" wire:target="refresh" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span wire:loading.remove wire:target="refresh">Refresh</span>
                <span wire:loading wire:target="refresh">Loading...</span>
            </button>

            <div class="w-px h-4 bg-zinc-800 mx-1"></div>

            {{-- Fetch --}}
            <button
                wire:click="fetchRemote"
                @if($remoteOperation) disabled @endif
                class="flex items-center gap-1 px-2 py-1 text-xs text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800 rounded transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                title="Fetch from remote (Cmd+Shift+F)"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                @if ($remoteOperation === 'fetch')
                    <span>Fetching...</span>
                @else
                    <span>Fetch</span>
                @endif
            </button>

            {{-- Pull --}}
            <button
                wire:click="pullRemote"
                @if($remoteOperation) disabled @endif
                class="flex items-center gap-1 px-2 py-1 text-xs rounded transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed
                    {{ $behind > 0 ? 'text-amber-400 hover:text-amber-300 hover:bg-amber-900/30' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800' }}"
                title="Pull from remote (Cmd+Shift+L){{ $behind > 0 ? ' (' . $behind . ' behind)' : '' }}"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
                @if ($remoteOperation === 'pull')
                    <span>Pulling...</span>
                @else
                    <span>Pull</span>
                    @if ($behind > 0)
                        <span class="text-[10px] bg-amber-900/50 text-amber-400 px-1 rounded">{{ $behind }}</span>
                    @endif
                @endif
            </button>

            {{-- Push --}}
            <button
                wire:click="pushRemote"
                @if($remoteOperation) disabled @endif
                class="flex items-center gap-1 px-2 py-1 text-xs rounded transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed
                    {{ $ahead > 0 ? 'text-emerald-400 hover:text-emerald-300 hover:bg-emerald-900/30' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800' }}"
                title="Push to remote (Cmd+Shift+P){{ $ahead > 0 ? ' (' . $ahead . ' ahead)' : '' }}{{ empty($upstream) && $currentBranch ? ' (will set upstream)' : '' }}"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                </svg>
                @if ($remoteOperation === 'push')
                    <span>Pushing...</span>
                @else
                    <span>Push</span>
                    @if ($ahead > 0)
                        <span class="text-[10px] bg-emerald-900/50 text-emerald-400 px-1 rounded">{{ $ahead }}</span>
                    @endif
                    @if (empty($upstream) && $currentBranch)
                        <span class="text-[10px] text-zinc-600">(set upstream)</span>
                    @endif
                @endif
            </button>

            {{-- Remote operation progress --}}
            @if ($remoteProgress)
                <span class="text-[10px] text-zinc-500 ml-2 truncate max-w-xs">{{ $remoteProgress }}</span>
            @endif
        </div>

        {{-- Loading overlay --}}
        @if ($isLoading)
            <div class="flex-1 flex items-center justify-center">
                <div class="flex flex-col items-center gap-3">
                    <svg class="w-6 h-6 text-zinc-600 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <div class="text-sm text-zinc-500">Loading repository...</div>
                </div>
            </div>
        @else
            {{-- Main split: graph (top) + status/diff (bottom) --}}
            <div class="flex-1 flex flex-col overflow-hidden" x-data="resizablePanel()">

                {{-- COMMIT GRAPH AREA --}}
                <div
                    class="flex-1 min-h-0 overflow-auto"
                    x-data="commitGraph(@js($commits), @js($selectedCommit))"
                    x-effect="updateGraph()"
                    wire:ignore.self
                >
                    @if (count($commits) > 0)
                        <div class="text-xs font-mono relative">
                            @foreach ($commits as $index => $commit)
                                <div
                                    wire:click="selectCommit('{{ $commit['hash'] }}')"
                                    class="flex items-center hover:bg-zinc-800/30 transition-colors cursor-pointer
                                        {{ $selectedCommit === $commit['hash'] ? 'bg-zinc-800/60' : '' }}"
                                    style="height: 28px;"
                                >
                                    {{-- Graph canvas column (rendered by Alpine) --}}
                                    <div class="shrink-0 relative" :style="'width:' + graphWidth + 'px'">
                                        @if ($index === 0)
                                            <canvas x-ref="graphCanvas" class="absolute top-0 left-0 pointer-events-none"></canvas>
                                        @endif
                                    </div>

                                    {{-- Refs (branches, tags) --}}
                                    <div class="flex items-center gap-1 shrink-0 px-1">
                                        @foreach ($commit['refs'] as $ref)
                                            @if (str_starts_with($ref, 'tag:'))
                                                <span class="px-1.5 py-0.5 text-[10px] rounded bg-amber-900/40 text-amber-400 border border-amber-800/50 whitespace-nowrap">{{ trim(str_replace('tag:', '', $ref)) }}</span>
                                            @elseif (str_contains($ref, 'HEAD'))
                                                <span class="px-1.5 py-0.5 text-[10px] rounded bg-cyan-900/40 text-cyan-400 border border-cyan-800/50 whitespace-nowrap">{{ $ref }}</span>
                                            @elseif (str_contains($ref, '/'))
                                                <span class="px-1.5 py-0.5 text-[10px] rounded bg-zinc-800 text-zinc-400 border border-zinc-700 whitespace-nowrap">{{ $ref }}</span>
                                            @else
                                                <span class="px-1.5 py-0.5 text-[10px] rounded bg-emerald-900/40 text-emerald-400 border border-emerald-800/50 whitespace-nowrap">{{ $ref }}</span>
                                            @endif
                                        @endforeach
                                    </div>

                                    {{-- Message --}}
                                    <div class="flex-1 truncate text-zinc-300 px-1">{{ $commit['message'] }}</div>

                                    {{-- Author + date --}}
                                    <div class="shrink-0 text-zinc-600 text-right px-2">
                                        <span>{{ $commit['author'] }}</span>
                                        <span class="ml-2">{{ $commit['dateHuman'] }}</span>
                                    </div>

                                    {{-- Hash --}}
                                    <div
                                        class="shrink-0 text-zinc-700 w-16 text-right pr-3 cursor-pointer hover:text-zinc-400 transition-colors"
                                        x-on:click.stop="navigator.clipboard.writeText('{{ $commit['hash'] }}'); $dispatch('toast', { message: 'Hash copied', type: 'success' })"
                                        title="Click to copy full hash"
                                    >{{ $commit['shortHash'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex items-center justify-center h-full text-sm text-zinc-600">
                            <div class="text-center">
                                <div>No commits yet</div>
                                <div class="text-xs text-zinc-700 mt-1">Stage files and create your first commit</div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- DRAG HANDLE --}}
                <div
                    @mousedown.prevent="startResize($event)"
                    class="h-1 shrink-0 bg-zinc-800 hover:bg-emerald-600 cursor-row-resize transition-colors relative group"
                >
                    <div class="absolute inset-x-0 -top-1 -bottom-1 group-hover:bg-transparent"></div>
                </div>

                {{-- BOTTOM PANEL: Status + Diff --}}
                <div class="shrink-0 flex overflow-hidden" :style="'height:' + panelHeight + 'px'">

                    {{-- FILE STATUS PANEL --}}
                    <div class="w-64 shrink-0 border-r border-zinc-800 flex flex-col overflow-hidden">

                        {{-- Staged files --}}
                        <div class="border-b border-zinc-800">
                            <div class="flex items-center justify-between px-3 py-1.5">
                                <span class="text-[10px] uppercase tracking-wider text-zinc-500 font-semibold">
                                    Staged
                                    @if (count($stagedFiles) > 0)
                                        <span class="text-zinc-600">({{ count($stagedFiles) }})</span>
                                    @endif
                                </span>
                                @if (count($stagedFiles) > 0)
                                    <button wire:click="unstageAll" class="text-[10px] text-zinc-600 hover:text-zinc-400 cursor-pointer">Unstage all</button>
                                @endif
                            </div>
                            <div class="max-h-20 overflow-y-auto">
                                @forelse ($stagedFiles as $file)
                                    <div
                                        wire:click="selectFile('{{ $file['path'] }}', true)"
                                        class="group flex items-center gap-1 px-3 py-0.5 text-xs hover:bg-zinc-800/50 cursor-pointer transition-colors
                                            {{ $selectedFile === $file['path'] && $selectedFileStaged ? 'bg-zinc-800/60' : '' }}"
                                    >
                                        <span class="w-1.5 text-emerald-500 shrink-0">{{ substr($file['indexStatus'], 0, 1) }}</span>
                                        <span class="truncate text-zinc-400">{{ $file['path'] }}</span>
                                        <button
                                            wire:click.stop="unstageFile('{{ $file['path'] }}')"
                                            class="ml-auto opacity-0 group-hover:opacity-100 text-zinc-600 hover:text-zinc-400 cursor-pointer"
                                            title="Unstage"
                                        >-</button>
                                    </div>
                                @empty
                                    <div class="px-3 py-1.5 text-[10px] text-zinc-700 italic">Nothing staged</div>
                                @endforelse
                            </div>
                        </div>

                        {{-- Unstaged + untracked files --}}
                        <div class="flex-1 overflow-y-auto">
                            <div class="flex items-center justify-between px-3 py-1.5">
                                <span class="text-[10px] uppercase tracking-wider text-zinc-500 font-semibold">
                                    Changes
                                    @php $changeCount = count($unstagedFiles) + count($untrackedFiles) + count($conflictedFiles); @endphp
                                    @if ($changeCount > 0)
                                        <span class="text-zinc-600">({{ $changeCount }})</span>
                                    @endif
                                </span>
                                @if ($changeCount > 0)
                                    <button wire:click="stageAll" class="text-[10px] text-zinc-600 hover:text-zinc-400 cursor-pointer">Stage all</button>
                                @endif
                            </div>

                            {{-- Conflicted files --}}
                            @foreach ($conflictedFiles as $file)
                                <div
                                    wire:click="selectFile('{{ $file['path'] }}')"
                                    class="group flex items-center gap-1 px-3 py-0.5 text-xs hover:bg-zinc-800/50 cursor-pointer transition-colors
                                        {{ $selectedFile === $file['path'] && !$selectedFileStaged ? 'bg-zinc-800/60' : '' }}"
                                >
                                    <span class="w-1.5 text-red-500 shrink-0">!</span>
                                    <span class="truncate text-red-400">{{ $file['path'] }}</span>
                                </div>
                            @endforeach

                            {{-- Unstaged files --}}
                            @foreach ($unstagedFiles as $file)
                                <div
                                    wire:click="selectFile('{{ $file['path'] }}')"
                                    class="group flex items-center gap-1 px-3 py-0.5 text-xs hover:bg-zinc-800/50 cursor-pointer transition-colors
                                        {{ $selectedFile === $file['path'] && !$selectedFileStaged ? 'bg-zinc-800/60' : '' }}"
                                >
                                    <span class="w-1.5 text-amber-500 shrink-0">{{ substr($file['workStatus'], 0, 1) }}</span>
                                    <span class="truncate text-zinc-400">{{ $file['path'] }}</span>
                                    <div class="ml-auto flex items-center gap-0.5 opacity-0 group-hover:opacity-100">
                                        <button
                                            wire:click.stop="stageFile('{{ $file['path'] }}')"
                                            class="text-zinc-600 hover:text-emerald-400 cursor-pointer"
                                            title="Stage"
                                        >+</button>
                                        <button
                                            wire:click.stop="discardFile('{{ $file['path'] }}')"
                                            wire:confirm="Discard changes to {{ $file['path'] }}?"
                                            class="text-zinc-600 hover:text-red-400 cursor-pointer"
                                            title="Discard"
                                        >&#x2715;</button>
                                    </div>
                                </div>
                            @endforeach

                            {{-- Untracked files --}}
                            @foreach ($untrackedFiles as $file)
                                <div
                                    wire:click="selectFile('{{ $file['path'] }}')"
                                    class="group flex items-center gap-1 px-3 py-0.5 text-xs hover:bg-zinc-800/50 cursor-pointer transition-colors
                                        {{ $selectedFile === $file['path'] && !$selectedFileStaged ? 'bg-zinc-800/60' : '' }}"
                                >
                                    <span class="w-1.5 text-zinc-600 shrink-0">?</span>
                                    <span class="truncate text-zinc-500">{{ $file['path'] }}</span>
                                    <button
                                        wire:click.stop="stageFile('{{ $file['path'] }}')"
                                        class="ml-auto opacity-0 group-hover:opacity-100 text-zinc-600 hover:text-emerald-400 cursor-pointer"
                                        title="Stage"
                                    >+</button>
                                </div>
                            @endforeach

                            @if ($changeCount === 0 && count($stagedFiles) === 0)
                                <div class="px-3 py-4 text-xs text-zinc-600 text-center flex flex-col items-center gap-1.5">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="italic">Working tree clean</span>
                                </div>
                            @endif
                        </div>

                        {{-- Commit box --}}
                        @if (count($stagedFiles) > 0)
                            <div class="border-t border-zinc-800 p-2">
                                <textarea
                                    wire:model="commitMessage"
                                    placeholder="Commit message..."
                                    rows="2"
                                    class="w-full bg-zinc-800 border border-zinc-700 rounded px-2 py-1 text-xs text-zinc-200 placeholder:text-zinc-600 resize-none focus:outline-none focus:border-emerald-600"
                                ></textarea>
                                <button
                                    wire:click="commit"
                                    wire:loading.attr="disabled"
                                    class="w-full mt-1 px-2 py-1 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-medium rounded transition-colors cursor-pointer disabled:opacity-50"
                                >
                                    <span wire:loading.remove wire:target="commit">Commit ({{ count($stagedFiles) }} file{{ count($stagedFiles) !== 1 ? 's' : '' }})</span>
                                    <span wire:loading wire:target="commit">Committing...</span>
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- DIFF PANEL --}}
                    <div class="flex-1 overflow-auto bg-zinc-950 font-mono text-xs">

                        {{-- Commit detail header (shown when a commit is selected) --}}
                        @if ($selectedCommitData)
                            <div class="bg-zinc-900 border-b border-zinc-800 px-4 py-3 font-sans">
                                <div class="text-sm text-zinc-200 mb-2 whitespace-pre-wrap">{{ $selectedCommitData['message'] }}</div>
                                <div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                                    <span class="text-zinc-600">Author</span>
                                    <span class="text-zinc-400">{{ $selectedCommitData['author'] }} <span class="text-zinc-600">&lt;{{ $selectedCommitData['email'] }}&gt;</span></span>

                                    <span class="text-zinc-600">Date</span>
                                    <span class="text-zinc-400">{{ \Carbon\Carbon::parse($selectedCommitData['date'])->format('M j, Y g:i A') }} <span class="text-zinc-600">({{ $selectedCommitData['dateHuman'] }})</span></span>

                                    <span class="text-zinc-600">Commit</span>
                                    <span
                                        class="text-zinc-500 font-mono cursor-pointer hover:text-zinc-300 transition-colors"
                                        x-on:click="navigator.clipboard.writeText('{{ $selectedCommitData['hash'] }}'); $dispatch('toast', { message: 'Hash copied', type: 'success' })"
                                        title="Click to copy hash"
                                    >{{ $selectedCommitData['hash'] }}</span>

                                    @if (count($selectedCommitData['parents']) > 0)
                                        <span class="text-zinc-600">{{ count($selectedCommitData['parents']) > 1 ? 'Parents' : 'Parent' }}</span>
                                        <span class="text-zinc-500 font-mono flex items-center gap-1.5">
                                            @foreach ($selectedCommitData['parents'] as $parent)
                                                <span
                                                    class="cursor-pointer hover:text-zinc-300 transition-colors"
                                                    x-on:click="navigator.clipboard.writeText('{{ $parent }}'); $dispatch('toast', { message: 'Parent hash copied', type: 'success' })"
                                                    title="Click to copy full parent hash"
                                                >{{ substr($parent, 0, 7) }}</span>
                                            @endforeach
                                        </span>
                                    @endif

                                    @if (count($selectedCommitData['refs']) > 0)
                                        <span class="text-zinc-600">Refs</span>
                                        <div class="flex items-center gap-1 flex-wrap">
                                            @foreach ($selectedCommitData['refs'] as $ref)
                                                @if (str_starts_with($ref, 'tag:'))
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded bg-amber-900/40 text-amber-400 border border-amber-800/50">{{ trim(str_replace('tag:', '', $ref)) }}</span>
                                                @elseif (str_contains($ref, 'HEAD'))
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded bg-cyan-900/40 text-cyan-400 border border-cyan-800/50">{{ $ref }}</span>
                                                @elseif (str_contains($ref, '/'))
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded bg-zinc-800 text-zinc-400 border border-zinc-700">{{ $ref }}</span>
                                                @else
                                                    <span class="px-1.5 py-0.5 text-[10px] rounded bg-emerald-900/40 text-emerald-400 border border-emerald-800/50">{{ $ref }}</span>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if (count($diffFiles) > 0)
                            @foreach ($diffFiles as $diff)
                                {{-- File header --}}
                                <div class="sticky top-0 bg-zinc-900 border-b border-zinc-800 px-3 py-1.5 flex items-center gap-2 z-10">
                                    <span class="text-zinc-400">{{ $diff['path'] }}</span>
                                    @if ($diff['oldPath'])
                                        <span class="text-zinc-600">&#8592; {{ $diff['oldPath'] }}</span>
                                    @endif
                                    <div class="ml-auto flex items-center gap-2">
                                        @if ($diff['additions'] > 0)
                                            <span class="text-emerald-500">+{{ $diff['additions'] }}</span>
                                        @endif
                                        @if ($diff['deletions'] > 0)
                                            <span class="text-red-500">-{{ $diff['deletions'] }}</span>
                                        @endif
                                    </div>
                                </div>

                                @if ($diff['isBinary'])
                                    <div class="px-3 py-4 text-zinc-600 italic">Binary file</div>
                                @else
                                    @foreach ($diff['hunks'] as $hunk)
                                        {{-- Hunk header --}}
                                        <div class="bg-zinc-900/50 text-cyan-700 px-3 py-0.5 border-b border-zinc-800/30">{{ $hunk['header'] }}</div>

                                        {{-- Diff lines --}}
                                        @foreach ($hunk['lines'] as $line)
                                            <div class="flex hover:bg-zinc-800/20
                                                {{ $line['type'] === 'add' ? 'bg-emerald-950/20' : '' }}
                                                {{ $line['type'] === 'remove' ? 'bg-red-950/20' : '' }}"
                                            >
                                                <span class="w-10 shrink-0 text-right pr-1 text-zinc-700 select-none border-r border-zinc-800/50">{{ $line['oldLine'] ?? '' }}</span>
                                                <span class="w-10 shrink-0 text-right pr-1 text-zinc-700 select-none border-r border-zinc-800/50">{{ $line['newLine'] ?? '' }}</span>
                                                <span class="w-4 shrink-0 text-center select-none
                                                    {{ $line['type'] === 'add' ? 'text-emerald-600' : '' }}
                                                    {{ $line['type'] === 'remove' ? 'text-red-600' : '' }}
                                                    {{ $line['type'] === 'context' ? 'text-zinc-700' : '' }}"
                                                >{{ $line['type'] === 'add' ? '+' : ($line['type'] === 'remove' ? '-' : ' ') }}</span>
                                                <span class="flex-1 whitespace-pre pl-1
                                                    {{ $line['type'] === 'add' ? 'text-emerald-400' : '' }}
                                                    {{ $line['type'] === 'remove' ? 'text-red-400' : '' }}
                                                    {{ $line['type'] === 'context' ? 'text-zinc-500' : '' }}"
                                                >{{ $line['content'] }}</span>
                                            </div>
                                        @endforeach
                                    @endforeach
                                @endif
                            @endforeach
                        @elseif ($selectedFile)
                            <div class="flex items-center justify-center h-full text-zinc-600 text-sm">No diff available</div>
                        @elseif ($selectedCommit)
                            <div class="flex items-center justify-center h-full text-zinc-600 text-sm">No changes in this commit</div>
                        @else
                            <div class="flex items-center justify-center h-full text-zinc-600 text-sm">Select a file or commit to view diff</div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Title bar slot --}}
    <x-slot:titleBar>
        {{ $repoName }}
        @if ($currentBranch)
            <span class="text-zinc-600 ml-1">/ {{ $currentBranch }}</span>
        @endif
    </x-slot:titleBar>
</div>
