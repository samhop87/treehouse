<div
    class="flex h-full min-h-0 overflow-hidden"
    x-data="repoView()"
    x-init="
        let lastRefresh = Date.now();
        window.addEventListener('focus', () => {
            if (! $wire.get('isActive')) return;
            if (Date.now() - lastRefresh > 3000) {
                lastRefresh = Date.now();
                $wire.handleWindowFocus();
            }
        });
    "
    @keydown.window="
        if (! $wire.get('isActive')) return;
        if ($event.metaKey && !$event.shiftKey && $event.key === 'r') {
            $event.preventDefault();
            $wire.handleShortcut('refresh');
        }
        if ($event.metaKey && $event.shiftKey && $event.key === 'p') {
            $event.preventDefault();
            $wire.handleShortcut('push');
        }
        if ($event.metaKey && $event.shiftKey && $event.key === 'f') {
            $event.preventDefault();
            $wire.handleShortcut('fetch');
        }
        if ($event.metaKey && $event.shiftKey && $event.key === 'l') {
            $event.preventDefault();
            $wire.handleShortcut('pull');
        }
        if ($event.metaKey && $event.key === 'Enter') {
            $event.preventDefault();
            $wire.handleShortcut('commit');
        }
        if ($event.key === 'Escape') {
            $wire.handleShortcut('escape');
        }
    "
    @mousedown.window="
        if ($wire.get('showContextMenu') && ! $event.target.closest('[data-context-menu-panel]') && ! $event.target.closest('[data-history-context-menu-trigger]')) {
            $wire.closeContextMenu();
        }
    "
>
    <aside class="flex w-[17.5rem] shrink-0 flex-col overflow-hidden border-r border-[#343944] bg-[#292d36]">
        <div class="border-b border-[#3b404b] px-3 py-3">
            <div class="flex items-center justify-between gap-3 text-sm font-medium text-gray-200">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span>Viewing <span class="font-semibold text-[#8fb3ff]">{{ count($commits) }}</span></span>
                </div>
                <span class="text-[10px] uppercase tracking-[0.18em] text-gray-500">Refs</span>
            </div>

            <div class="relative mt-3">
                <input
                    x-model.live="referenceFilter"
                    type="text"
                    placeholder="Filter (⌘ + Option + F)"
                    class="w-full rounded-md border border-[#3b404b] bg-[#21252d] px-3 py-2 pr-9 text-sm text-gray-200 placeholder:text-gray-500 focus:border-violet-600 focus:outline-none"
                >
                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div class="border-b border-[#343944] px-3 py-2.5">
                @if ($showCreateBranch)
                    <div class="rounded-lg border border-[#3b404b] bg-[#1f232b] p-2">
                        <input
                            wire:model="newBranchName"
                            wire:keydown.enter="createBranch"
                            wire:keydown.escape="closeCreateBranch"
                            type="text"
                            placeholder="Branch name..."
                            class="w-full rounded border border-[#3a3f4d] bg-[#151922] px-2 py-1 text-xs text-gray-200 placeholder:text-gray-600 focus:border-violet-600 focus:outline-none"
                            autofocus
                        >
                        <input
                            wire:model="newBranchStartPoint"
                            wire:keydown.enter="createBranch"
                            type="text"
                            placeholder="Start point (optional)"
                            class="mt-1 w-full rounded border border-[#3a3f4d] bg-[#151922] px-2 py-1 text-xs text-gray-200 placeholder:text-gray-600 focus:border-violet-600 focus:outline-none"
                        >
                        <div class="mt-2 flex gap-1">
                            <button
                                wire:click="createBranch"
                                class="flex-1 rounded bg-violet-600 px-2 py-1 text-[10px] font-medium text-white transition-colors hover:bg-violet-500 cursor-pointer"
                            >
                                Create & Switch
                            </button>
                            <button
                                wire:click="closeCreateBranch"
                                class="rounded bg-[#171b23] px-2 py-1 text-[10px] text-gray-300 transition-colors hover:bg-[#202530] cursor-pointer"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                @else
                    <button
                        wire:click="openCreateBranch"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium text-gray-300 transition-colors hover:bg-[#323743] hover:text-gray-100 cursor-pointer"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        New branch
                    </button>
                @endif
            </div>

            @if ($showMergeConfirm)
                <div class="border-b border-[#343944] px-3 py-2.5">
                    <div class="rounded-lg border border-violet-800/40 bg-[#1d2230] p-2">
                        <div class="mb-1.5 text-xs text-gray-300">
                            Merge <span class="font-medium text-gray-100">{{ $mergeBranchName }}</span> into <span class="font-medium text-gray-100">{{ $currentBranch }}</span>?
                        </div>
                        <div class="flex gap-1">
                            <button
                                wire:click="mergeBranch"
                                class="flex-1 rounded bg-violet-600 px-2 py-1 text-[10px] font-medium text-white transition-colors hover:bg-violet-500 cursor-pointer"
                            >
                                Merge
                            </button>
                            <button
                                wire:click="closeMerge"
                                class="rounded bg-[#141420] px-2 py-1 text-[10px] text-gray-300 transition-colors hover:bg-[#1a1a2e] cursor-pointer"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            @if ($status && $status['hasConflicts'])
                <div class="border-b border-[#343944] px-3 py-2.5">
                    <div class="rounded-lg border border-red-800/50 bg-red-900/20 p-2">
                        <div class="mb-1 text-xs text-red-400">Merge conflicts detected</div>
                        <button
                            wire:click="mergeAbort"
                            wire:confirm="Abort the current merge? All merge changes will be lost."
                            class="w-full rounded bg-red-700 px-2 py-1 text-[10px] font-medium text-white transition-colors hover:bg-red-600 cursor-pointer"
                        >
                            Abort Merge
                        </button>
                    </div>
                </div>
            @endif

            <div class="divide-y divide-[#343944]">
                <div>
                    <button
                        type="button"
                        @click="toggleReferenceSection('local')"
                        class="flex w-full items-center gap-2 px-3 py-3 text-left transition-colors hover:bg-[#303541] cursor-pointer"
                    >
                        <svg
                            class="h-3.5 w-3.5 shrink-0 text-gray-500 transition-transform"
                            :class="referenceSections.local ? 'rotate-90 text-gray-300' : ''"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                        <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18M7 5v14m10-14v14M5 19h14"/>
                        </svg>
                        <span class="flex-1 text-xs font-semibold uppercase tracking-[0.12em] text-gray-300">Local</span>
                        <span class="text-sm font-semibold leading-none text-[#9fb3ff]">{{ count($localBranches) }}</span>
                    </button>

                    <div x-show="referenceSections.local" x-collapse class="border-t border-[#343944] bg-[#232730] px-2 py-2">
                        @forelse ($localBranches as $branch)
                            <div
                                wire:click="selectBranch('{{ $branch['name'] }}')"
                                @if (! $branch['isCurrent']) wire:dblclick="checkoutLocalBranch('{{ $branch['name'] }}')" @endif
                                x-show="matchesReference(@js($branch['name']))"
                                class="group mb-1 rounded-md border px-2 py-1.5 text-xs transition-colors cursor-pointer {{ $selectedHistoryType === 'branch' && $selectedBranch === $branch['name'] ? 'border-violet-600/40 bg-violet-900/25 text-gray-100' : ($branch['isCurrent'] ? 'border-violet-700/30 bg-violet-900/20 text-gray-100 hover:bg-violet-900/25' : 'border-transparent text-gray-400 hover:bg-[#1a1f27] hover:text-gray-300') }}"
                                title="{{ $branch['isCurrent'] ? 'Click to inspect this branch' : 'Click to inspect this branch. Double-click to switch branches.' }}"
                            >
                                <div class="flex items-center gap-1.5">
                                    @if ($branch['isCurrent'])
                                        <span class="h-2 w-2 shrink-0 rounded-full bg-violet-500 shadow-[0_0_6px_rgba(139,92,246,0.45)]"></span>
                                    @else
                                        <svg class="h-3 w-3 shrink-0 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 3v12m0 0a3 3 0 103 3 3 3 0 00-3-3zm12-6a3 3 0 10-3-3 3 3 0 003 3zm0 0v2a2 2 0 01-2 2H9"/>
                                        </svg>
                                    @endif
                                    <span class="min-w-0 flex-1 truncate">{{ $branch['name'] }}</span>
                                    @if ($branch['ahead'])
                                        <span class="text-[10px] text-violet-400">+{{ $branch['ahead'] }}</span>
                                    @endif
                                    @if ($branch['behind'])
                                        <span class="text-[10px] text-cyan-400">-{{ $branch['behind'] }}</span>
                                    @endif
                                </div>

                                <div class="mt-1 flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                    <button
                                        x-on:click.stop="navigator.clipboard.writeText('{{ $branch['name'] }}'); $dispatch('toast', { message: 'Branch name copied', type: 'success' })"
                                        class="rounded p-0.5 text-gray-600 hover:text-gray-300 cursor-pointer"
                                        title="Copy branch name"
                                    >
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                    @if (! $branch['isCurrent'])
                                        <button
                                            wire:click.stop="openMerge('{{ $branch['name'] }}')"
                                            class="rounded p-0.5 text-gray-600 hover:text-violet-400 cursor-pointer"
                                            title="Merge into {{ $currentBranch }}"
                                        >
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                            </svg>
                                        </button>
                                        <button
                                            wire:click.stop="deleteBranch('{{ $branch['name'] }}')"
                                            wire:confirm="Delete branch '{{ $branch['name'] }}'?"
                                            class="rounded p-0.5 text-gray-600 hover:text-red-400 cursor-pointer"
                                            title="Delete branch"
                                        >
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="px-2 py-3 text-xs italic text-gray-600">No local branches</div>
                        @endforelse
                    </div>
                </div>

                <div>
                    <button
                        type="button"
                        @click="toggleReferenceSection('remote')"
                        class="flex w-full items-center gap-2 px-3 py-3 text-left transition-colors hover:bg-[#303541] cursor-pointer"
                    >
                        <svg
                            class="h-3.5 w-3.5 shrink-0 text-gray-500 transition-transform"
                            :class="referenceSections.remote ? 'rotate-90 text-gray-300' : ''"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                        <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 014-4h9a5 5 0 010 10H7a4 4 0 01-4-4 4 4 0 014-4m0 0V9a4 4 0 118 0v2"/>
                        </svg>
                        <span class="flex-1 text-xs font-semibold uppercase tracking-[0.12em] text-gray-300">Remote</span>
                        <span class="text-sm font-semibold leading-none text-[#9fb3ff]">{{ count($remoteBranches) }}</span>
                    </button>

                    <div x-show="referenceSections.remote" x-collapse class="border-t border-[#343944] bg-[#232730] px-2 py-2">
                        @forelse ($remoteBranches as $branch)
                            <div
                                wire:click="selectBranch('{{ $branch['name'] }}')"
                                wire:dblclick="checkoutRemoteBranch('{{ $branch['name'] }}')"
                                x-show="matchesReference(@js($branch['name']))"
                                class="group mb-1 rounded-md border px-2 py-1.5 text-xs transition-colors cursor-pointer {{ $selectedHistoryType === 'branch' && $selectedBranch === $branch['name'] ? 'border-violet-600/40 bg-violet-900/25 text-gray-200' : 'border-transparent text-gray-500 hover:bg-[#1a1f27] hover:text-gray-300' }}"
                                title="Click to inspect this branch. Double-click to checkout a local tracking branch."
                            >
                                <div class="flex items-center gap-1.5">
                                    <svg class="h-3 w-3 shrink-0 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 3v12m0 0a3 3 0 103 3 3 3 0 00-3-3zm12-6a3 3 0 10-3-3 3 3 0 003 3zm0 0v2a2 2 0 01-2 2H9"/>
                                    </svg>
                                    <span class="min-w-0 flex-1 truncate">{{ $branch['name'] }}</span>
                                    @if ($branch['hasLocalPair'])
                                        <span class="rounded border border-[#3b404b] bg-[#1b1f27] px-1.5 py-0.5 text-[9px] uppercase tracking-[0.16em] text-gray-500">Local</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="px-2 py-3 text-xs italic text-gray-600">No remote branches</div>
                        @endforelse
                    </div>
                </div>

                <div>
                    <button
                        type="button"
                        @click="toggleReferenceSection('stashes')"
                        class="flex w-full items-center gap-2 px-3 py-3 text-left transition-colors hover:bg-[#303541] cursor-pointer"
                    >
                        <svg
                            class="h-3.5 w-3.5 shrink-0 text-gray-500 transition-transform"
                            :class="referenceSections.stashes ? 'rotate-90 text-gray-300' : ''"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                        <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7L4 7m16 0l-2 10H6L4 7m4 0V5a2 2 0 012-2h4a2 2 0 012 2v2"/>
                        </svg>
                        <span class="flex-1 text-xs font-semibold uppercase tracking-[0.12em] text-gray-300">Stashes</span>
                        <span class="text-sm font-semibold leading-none text-[#9fb3ff]">{{ count($stashes) }}</span>
                    </button>

                    <div x-show="referenceSections.stashes" x-collapse class="border-t border-[#343944] bg-[#232730] py-2">
                        <div class="px-3 pb-2">
                            @if ($showCreateStash)
                                <div class="rounded-lg border border-[#3b404b] bg-[#1f232b] p-2">
                                    <textarea
                                        wire:model="newStashMessage"
                                        wire:keydown.enter="createStash"
                                        wire:keydown.escape="closeCreateStash"
                                        placeholder="Stash message (optional)..."
                                        rows="2"
                                        class="w-full resize-none rounded border border-[#3a3f4d] bg-[#151922] px-2 py-1 text-xs text-gray-200 placeholder:text-gray-600 focus:border-violet-600 focus:outline-none"
                                        autofocus
                                    ></textarea>
                                    <div class="mt-2 flex gap-1">
                                        <button
                                            wire:click="createStash"
                                            class="flex-1 rounded bg-violet-600 px-2 py-1 text-[10px] font-medium text-white transition-colors hover:bg-violet-500 cursor-pointer"
                                        >
                                            Stash changes
                                        </button>
                                        <button
                                            wire:click="closeCreateStash"
                                            class="rounded bg-[#141420] px-2 py-1 text-[10px] text-gray-300 transition-colors hover:bg-[#1a1a2e] cursor-pointer"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            @else
                                <button
                                    wire:click="openCreateStash"
                                    class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium text-gray-300 transition-colors hover:bg-[#323743] hover:text-gray-100 cursor-pointer"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Stash changes
                                </button>
                            @endif
                        </div>

                        @forelse ($stashes as $stash)
                            <div x-show="matchesReference(@js($stash['ref'] . ' ' . $stash['message']))" class="group px-3 py-1.5 text-xs text-gray-400 transition-colors hover:bg-[#1a1f27]">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono text-gray-500">{{ $stash['ref'] }}</span>
                                    <div class="ml-auto flex items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                                        <button
                                            wire:click="stashApply('{{ $stash['ref'] }}')"
                                            class="rounded px-1 py-0.5 text-[10px] text-gray-600 hover:bg-[#1a1a2e] hover:text-violet-400 cursor-pointer"
                                            title="Apply"
                                        >
                                            Apply
                                        </button>
                                        <button
                                            wire:click="stashPop('{{ $stash['ref'] }}')"
                                            class="rounded px-1 py-0.5 text-[10px] text-gray-600 hover:bg-[#1a1a2e] hover:text-cyan-400 cursor-pointer"
                                            title="Pop"
                                        >
                                            Pop
                                        </button>
                                        <button
                                            wire:click="stashDrop('{{ $stash['ref'] }}')"
                                            wire:confirm="Drop {{ $stash['ref'] }}? This cannot be undone."
                                            class="rounded px-1 py-0.5 text-[10px] text-gray-600 hover:bg-[#1a1a2e] hover:text-red-400 cursor-pointer"
                                            title="Drop"
                                        >
                                            Drop
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-0.5 truncate text-gray-500">{{ $stash['message'] }}</div>
                            </div>
                        @empty
                            <div class="px-3 py-4 text-center text-xs italic text-gray-600">No stashes</div>
                        @endforelse
                    </div>
                </div>

                <div>
                    <button
                        type="button"
                        @click="toggleReferenceSection('tags')"
                        class="flex w-full items-center gap-2 px-3 py-3 text-left transition-colors hover:bg-[#303541] cursor-pointer"
                    >
                        <svg
                            class="h-3.5 w-3.5 shrink-0 text-gray-500 transition-transform"
                            :class="referenceSections.tags ? 'rotate-90 text-gray-300' : ''"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                        <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        <span class="flex-1 text-xs font-semibold uppercase tracking-[0.12em] text-gray-300">Tags</span>
                        <span class="text-sm font-semibold leading-none text-[#9fb3ff]">{{ count($tags) }}</span>
                    </button>

                    <div x-show="referenceSections.tags" x-collapse class="border-t border-[#343944] bg-[#232730] py-2">
                        <div class="px-3 pb-2">
                            @if ($showCreateTag)
                                <div class="rounded-lg border border-[#3b404b] bg-[#1f232b] p-2">
                                    <input
                                        wire:model="newTagName"
                                        wire:keydown.escape="closeCreateTag"
                                        type="text"
                                        placeholder="Tag name..."
                                        class="w-full rounded border border-[#3a3f4d] bg-[#151922] px-2 py-1 text-xs text-gray-200 placeholder:text-gray-600 focus:border-teal-600 focus:outline-none"
                                        autofocus
                                    >
                                    <input
                                        wire:model="newTagRef"
                                        type="text"
                                        placeholder="Commit (optional)"
                                        class="mt-1 w-full rounded border border-[#3a3f4d] bg-[#151922] px-2 py-1 text-xs text-gray-200 placeholder:text-gray-600 focus:border-teal-600 focus:outline-none"
                                    >
                                    <label class="mt-1.5 flex items-center gap-1.5 text-xs text-gray-400 cursor-pointer">
                                        <input
                                            wire:model.live="newTagAnnotated"
                                            type="checkbox"
                                            class="h-3 w-3 rounded border-gray-600 bg-[#06060c] text-teal-500 focus:ring-0"
                                        >
                                        Annotated tag
                                    </label>
                                    @if ($newTagAnnotated)
                                        <textarea
                                            wire:model="newTagMessage"
                                            placeholder="Tag message..."
                                            rows="2"
                                            class="mt-1 w-full resize-none rounded border border-[#2a2a42] bg-[#06060c] px-2 py-1 text-xs text-gray-200 placeholder:text-gray-600 focus:border-teal-600 focus:outline-none"
                                        ></textarea>
                                    @endif
                                    <div class="mt-2 flex gap-1">
                                        <button
                                            wire:click="createTag"
                                            class="flex-1 rounded bg-teal-600 px-2 py-1 text-[10px] font-medium text-white transition-colors hover:bg-teal-500 cursor-pointer"
                                        >
                                            Create Tag
                                        </button>
                                        <button
                                            wire:click="closeCreateTag"
                                            class="rounded bg-[#141420] px-2 py-1 text-[10px] text-gray-300 transition-colors hover:bg-[#1a1a2e] cursor-pointer"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-1">
                                    <button
                                        wire:click="openCreateTag"
                                        class="flex flex-1 items-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium text-gray-300 transition-colors hover:bg-[#323743] hover:text-gray-100 cursor-pointer"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        New tag
                                    </button>
                                    @if (count($tags) > 0)
                                        <button
                                            wire:click="pushAllTags"
                                            wire:confirm="Push all tags to origin?"
                                            class="rounded px-2 py-1 text-[10px] text-gray-600 transition-colors hover:bg-[#1a1f27] hover:text-gray-400 cursor-pointer"
                                            title="Push all tags"
                                        >
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @forelse ($tags as $tag)
                            <div x-show="matchesReference(@js($tag['name']))" class="group flex items-center gap-1.5 px-3 py-1.5 text-xs text-gray-400 transition-colors hover:bg-[#1a1f27]">
                                <svg class="h-3 w-3 shrink-0 {{ $tag['isAnnotated'] ? 'text-teal-500' : 'text-gray-600' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                                <span class="min-w-0 flex-1 truncate" title="{{ $tag['isAnnotated'] ? 'Annotated: ' . $tag['message'] : 'Lightweight' }}">{{ $tag['name'] }}</span>
                                <span class="shrink-0 font-mono text-[10px] text-gray-700">{{ substr($tag['commitHash'], 0, 7) }}</span>
                                <div class="flex items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                                    <button
                                        x-on:click="navigator.clipboard.writeText('{{ $tag['name'] }}'); $dispatch('toast', { message: 'Tag name copied', type: 'success' })"
                                        class="rounded p-0.5 text-gray-600 hover:text-gray-300 cursor-pointer"
                                        title="Copy tag name"
                                    >
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                    <button
                                        wire:click="pushTag('{{ $tag['name'] }}')"
                                        wire:confirm="Push tag '{{ $tag['name'] }}' to origin?"
                                        class="rounded p-0.5 text-gray-600 hover:text-teal-400 cursor-pointer"
                                        title="Push tag"
                                    >
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                        </svg>
                                    </button>
                                    <button
                                        wire:click="deleteTag('{{ $tag['name'] }}')"
                                        wire:confirm="Delete tag '{{ $tag['name'] }}'?"
                                        class="rounded p-0.5 text-gray-600 hover:text-red-400 cursor-pointer"
                                        title="Delete tag"
                                    >
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="px-3 py-4 text-center text-xs italic text-gray-600">No tags</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <div class="flex-1 min-w-0 flex flex-col overflow-hidden">
        @if ($errorMessage)
            <div class="border-b border-red-800 bg-red-900/30 px-4 py-2 text-sm text-red-400 flex items-center justify-between">
                <span>{{ $errorMessage }}</span>
                <button wire:click="$set('errorMessage', '')" class="text-red-500 hover:text-red-300 cursor-pointer">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        <div class="flex items-stretch gap-1 border-b border-[#3a3f4b] bg-[#343943] px-3 py-1.5">
            <button
                wire:click="refresh"
                wire:loading.attr="disabled"
                class="flex min-w-[4.75rem] flex-col items-center justify-center gap-1 rounded px-2 py-1.5 text-[11px] text-gray-300 transition-colors hover:bg-[#2c3039] hover:text-gray-100 cursor-pointer"
                title="Refresh (Cmd+R)"
            >
                <span wire:loading.remove wire:target="refresh" class="leading-none">Refresh</span>
                <span wire:loading wire:target="refresh" class="leading-none">Loading</span>
                <svg class="h-5 w-5" wire:loading.class="animate-spin" wire:target="refresh" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>

            <button
                wire:click="fetchRemote"
                @if ($remoteOperation) disabled @endif
                class="flex min-w-[4.75rem] flex-col items-center justify-center gap-1 rounded px-2 py-1.5 text-[11px] text-gray-300 transition-colors hover:bg-[#2c3039] hover:text-gray-100 disabled:cursor-not-allowed disabled:opacity-40 cursor-pointer"
                title="Fetch from remote (Cmd+Shift+F)"
            >
                <span class="leading-none">{{ $remoteOperation === 'fetch' ? 'Fetching' : 'Fetch' }}</span>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
            </button>

            <button
                wire:click="pullRemote"
                @if ($remoteOperation) disabled @endif
                class="flex min-w-[4.75rem] flex-col items-center justify-center gap-1 rounded px-2 py-1.5 text-[11px] transition-colors disabled:cursor-not-allowed disabled:opacity-40 cursor-pointer {{ $behind > 0 ? 'text-cyan-300 hover:bg-cyan-900/20 hover:text-cyan-200' : 'text-gray-300 hover:bg-[#2c3039] hover:text-gray-100' }}"
                title="Pull from remote (Cmd+Shift+L)"
            >
                <div class="flex items-center gap-1 leading-none">
                    <span>{{ $remoteOperation === 'pull' ? 'Pulling' : 'Pull' }}</span>
                    @if ($behind > 0 && $remoteOperation !== 'pull')
                        <span class="rounded bg-cyan-900/40 px-1 text-[9px] text-cyan-300">{{ $behind }}</span>
                    @endif
                </div>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
            </button>

            <button
                wire:click="pushRemote"
                @if ($remoteOperation) disabled @endif
                class="flex min-w-[4.75rem] flex-col items-center justify-center gap-1 rounded px-2 py-1.5 text-[11px] transition-colors disabled:cursor-not-allowed disabled:opacity-40 cursor-pointer {{ $ahead > 0 ? 'text-violet-300 hover:bg-violet-900/20 hover:text-violet-200' : 'text-gray-300 hover:bg-[#2c3039] hover:text-gray-100' }}"
                title="Push to remote (Cmd+Shift+P)"
            >
                <div class="flex items-center gap-1 leading-none">
                    <span>{{ $remoteOperation === 'push' ? 'Pushing' : 'Push' }}</span>
                    @if ($ahead > 0 && $remoteOperation !== 'push')
                        <span class="rounded bg-violet-900/40 px-1 text-[9px] text-violet-300">{{ $ahead }}</span>
                    @endif
                </div>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                </svg>
            </button>

            <div class="ml-auto flex min-w-0 items-center gap-3 border-l border-[#444a56] pl-4">
                @if ($remoteProgress)
                    <span class="truncate text-[10px] text-gray-500">{{ $remoteProgress }}</span>
                @endif

                <div class="min-w-0 text-right">
                    <div class="text-[10px] uppercase tracking-[0.18em] text-gray-500">Status</div>
                    @if ($isDetached)
                        <div class="mt-1 truncate text-sm font-medium text-cyan-300">Detached HEAD</div>
                        <div class="truncate text-[10px] text-gray-600">{{ $status['headHash'] ?? '' }}</div>
                    @else
                        <div class="mt-1 truncate text-sm font-medium text-gray-100">{{ $currentBranch }}</div>
                        <div class="truncate text-[10px] text-gray-600">
                            {{ $upstream ?: 'No upstream' }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($isLoading)
            <div class="flex flex-1 items-center justify-center">
                <div class="flex flex-col items-center gap-3">
                    <svg class="h-6 w-6 animate-spin text-violet-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <div class="text-sm text-gray-500">Loading repository...</div>
                </div>
            </div>
        @else
            @php
                $unstagedTotal = count($unstagedFiles) + count($untrackedFiles) + count($conflictedFiles);
                $totalFileChanges = count($stagedFiles) + $unstagedTotal;
            @endphp

            <div class="flex-1 min-h-0 flex overflow-hidden" x-data="repoLayout()">
                <div class="flex-1 min-w-0 overflow-hidden bg-[#06060c]">
                    @if ($selectedFile)
                        <div class="flex h-full flex-col overflow-hidden">
                            <div class="border-b border-[#1e1e32] bg-[#0e0e18] px-4 py-3">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0 flex-1">
                                        @if ($selectedHistoryType === 'commit' && $selectedCommitData)
                                            <div class="text-[10px] uppercase tracking-[0.2em] text-gray-600">Commit Diff</div>
                                            <div class="mt-1 text-sm text-gray-200 whitespace-pre-wrap">{{ $selectedCommitData['message'] }}</div>
                                            <div class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                                                <span class="text-gray-600">Commit</span>
                                                <span
                                                    class="cursor-pointer font-mono text-gray-400 transition-colors hover:text-gray-200"
                                                    x-on:click="navigator.clipboard.writeText('{{ $selectedCommitData['hash'] }}'); $dispatch('toast', { message: 'Hash copied', type: 'success' })"
                                                    title="Click to copy hash"
                                                >{{ $selectedCommitData['hash'] }}</span>

                                                <span class="text-gray-600">Author</span>
                                                <span class="text-gray-400">{{ $selectedCommitData['author'] }} <span class="text-gray-600">&lt;{{ $selectedCommitData['email'] }}&gt;</span></span>

                                                <span class="text-gray-600">Date</span>
                                                <span class="text-gray-400">{{ \Carbon\Carbon::parse($selectedCommitData['date'])->format('M j, Y g:i A') }} <span class="text-gray-600">({{ $selectedCommitData['dateHuman'] }})</span></span>

                                                <span class="text-gray-600">File</span>
                                                <span class="truncate text-gray-200">{{ $selectedFile }}</span>
                                            </div>
                                        @elseif ($selectedHistoryType === 'branch' && $selectedBranchData)
                                            <div class="text-[10px] uppercase tracking-[0.2em] text-gray-600">Branch Diff</div>
                                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                                <span class="rounded border border-violet-800/50 bg-violet-900/30 px-2 py-0.5 text-xs text-violet-300">{{ $selectedBranchData['name'] }}</span>
                                                <span class="rounded border border-[#2a2a42] bg-[#1a1a2e] px-2 py-0.5 text-[10px] uppercase tracking-[0.15em] text-gray-500">{{ $selectedBranchData['isRemote'] ? 'Remote' : 'Local' }}</span>
                                            </div>
                                            <div class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                                                <span class="text-gray-600">Compared To</span>
                                                <span class="text-gray-400">{{ $isDetached ? 'HEAD' : ($currentBranch ?? 'HEAD') }}</span>

                                                <span class="text-gray-600">File</span>
                                                <span class="truncate text-gray-200">{{ $selectedFile }}</span>
                                            </div>
                                        @else
                                            <div class="text-[10px] uppercase tracking-[0.2em] text-gray-600">
                                                {{ $selectedFileStaged ? 'Staged Diff' : 'Working Tree Diff' }}
                                            </div>
                                            <div class="mt-1 truncate text-sm font-medium text-gray-200">{{ $selectedFile }}</div>
                                        @endif
                                    </div>

                                    <button
                                        wire:click="clearFileSelection"
                                        class="shrink-0 rounded border border-[#2a2a42] bg-[#1a1a2e] px-2 py-1 text-xs text-gray-400 transition-colors hover:bg-[#202035] hover:text-gray-200 cursor-pointer"
                                    >
                                        {{ $selectedHistoryType ? 'Back to files' : 'Back to changes' }}
                                    </button>
                                </div>
                            </div>

                            <div class="flex-1 overflow-auto font-mono text-xs">
                                @if (count($diffFiles) > 0)
                                    @foreach ($diffFiles as $diff)
                                        <div class="sticky top-0 z-10 flex items-center gap-2 border-b border-[#1e1e32] bg-[#0e0e18] px-3 py-1.5">
                                            <span class="text-gray-400">{{ $diff['path'] }}</span>
                                            @if ($diff['oldPath'])
                                                <span class="text-gray-600">&#8592; {{ $diff['oldPath'] }}</span>
                                            @endif
                                            <div class="ml-auto flex items-center gap-2">
                                                @if ($diff['additions'] > 0)
                                                    <span class="text-teal-400">+{{ $diff['additions'] }}</span>
                                                @endif
                                                @if ($diff['deletions'] > 0)
                                                    <span class="text-red-500">-{{ $diff['deletions'] }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        @if ($diff['isBinary'])
                                            <div class="px-3 py-4 italic text-gray-600">Binary file</div>
                                        @else
                                            @foreach ($diff['hunks'] as $hunk)
                                                <div class="border-b border-[#1e1e32]/30 bg-[#0e0e18]/50 px-3 py-0.5 text-purple-500/70">{{ $hunk['header'] }}</div>

                                                @foreach ($hunk['lines'] as $line)
                                                    <div class="flex hover:bg-[#1a1a2e]/20 {{ $line['type'] === 'add' ? 'bg-teal-950/15' : '' }} {{ $line['type'] === 'remove' ? 'bg-red-950/20' : '' }}">
                                                        <span class="w-10 shrink-0 select-none border-r border-[#1e1e32]/50 pr-1 text-right text-gray-700">{{ $line['oldLine'] ?? '' }}</span>
                                                        <span class="w-10 shrink-0 select-none border-r border-[#1e1e32]/50 pr-1 text-right text-gray-700">{{ $line['newLine'] ?? '' }}</span>
                                                        <span class="w-4 shrink-0 select-none text-center {{ $line['type'] === 'add' ? 'text-teal-600' : '' }} {{ $line['type'] === 'remove' ? 'text-red-600' : '' }} {{ $line['type'] === 'context' ? 'text-gray-700' : '' }}">
                                                            {{ $line['type'] === 'add' ? '+' : ($line['type'] === 'remove' ? '-' : ' ') }}
                                                        </span>
                                                        <span class="flex-1 whitespace-pre pl-1 {{ $line['type'] === 'add' ? 'text-teal-400' : '' }} {{ $line['type'] === 'remove' ? 'text-red-400' : '' }} {{ $line['type'] === 'context' ? 'text-gray-500' : '' }}">
                                                            {{ $line['content'] }}
                                                        </span>
                                                    </div>
                                                @endforeach
                                            @endforeach
                                        @endif
                                    @endforeach
                                @else
                                    <div class="flex h-full items-center justify-center text-sm text-gray-600">No diff available</div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div
                            class="h-full overflow-auto"
                            x-data="commitGraph()"
                            x-effect="$wire.commits; $wire.selectedCommit; updateGraph()"
                            wire:ignore.self
                        >
                            @if (count($commits) > 0)
                                <div
                                    class="sticky top-0 z-20 grid border-b border-[#353a46] bg-[#242933] text-[10px] font-medium uppercase tracking-[0.18em] text-gray-500"
                                    :style="'grid-template-columns: 12rem ' + Math.max(graphWidth, graphColumnWidth) + 'px minmax(0,1fr);'"
                                >
                                    <div class="border-r border-[#1e1e32] px-4 py-2">Branch / Tag</div>
                                    <div class="border-r border-[#1e1e32] px-4 py-2">Graph</div>
                                    <div class="px-4 py-2">Commit Message</div>
                                </div>

                                <div class="relative text-xs font-mono">
                                    <div
                                        class="pointer-events-none absolute inset-y-0"
                                        :style="'left: 12rem; width: ' + Math.max(graphWidth, graphColumnWidth) + 'px;'"
                                    >
                                        <canvas x-ref="graphCanvas" wire:ignore class="absolute top-0 left-0"></canvas>
                                    </div>

                                    @foreach ($commits as $commit)
                                        <div
                                            wire:click="selectCommit('{{ $commit['hash'] }}')"
                                            wire:dblclick="checkoutCommit('{{ $commit['hash'] }}')"
                                            x-on:contextmenu.prevent.stop="$wire.openCommitContextMenu('{{ $commit['hash'] }}', $event.clientX, $event.clientY)"
                                            data-history-context-menu-trigger
                                            class="grid h-10 cursor-pointer items-stretch border-b border-[#232833] transition-colors hover:bg-[#202531] {{ $selectedHistoryType === 'commit' && $selectedCommit === $commit['hash'] ? 'bg-violet-900/20' : '' }}"
                                            :style="'grid-template-columns: 12rem ' + Math.max(graphWidth, graphColumnWidth) + 'px minmax(0,1fr);'"
                                            title="Click to inspect changed files. Double-click to checkout this commit."
                                        >
                                            <div class="flex min-w-0 items-center overflow-hidden border-r border-[#1e1e32] px-3 py-1.5">
                                                <div class="flex min-w-0 flex-nowrap items-center gap-1.5 overflow-hidden">
                                                    @foreach ($commit['refs'] as $ref)
                                                        @php
                                                            $graphRefIsTag = str_starts_with($ref, 'tag:');
                                                            $graphRefIsPointer = $ref === 'HEAD' || str_ends_with($ref, '/HEAD');
                                                            $graphRefLabel = $graphRefIsTag ? trim(str_replace('tag:', '', $ref)) : $ref;
                                                        @endphp

                                                        @if ($graphRefIsTag)
                                                            <span class="shrink-0 whitespace-nowrap rounded border border-teal-800/50 bg-teal-900/40 px-2.5 py-1 text-[11px] font-semibold text-teal-300">{{ $graphRefLabel }}</span>
                                                        @elseif ($graphRefIsPointer)
                                                            <span class="shrink-0 whitespace-nowrap rounded border border-cyan-800/50 bg-cyan-900/40 px-2.5 py-1 text-[11px] font-semibold text-cyan-300">{{ $graphRefLabel }}</span>
                                                        @else
                                                            @php
                                                                $graphBranchContextTarget = $this->contextMenuBranchTarget($ref);
                                                            @endphp
                                                            <button
                                                                wire:click.stop="selectGraphRef('{{ $ref }}')"
                                                                wire:dblclick.stop="checkoutGraphRef('{{ $ref }}')"
                                                                x-on:contextmenu.prevent.stop="$wire.openBranchContextMenu(@js($ref), $event.clientX, $event.clientY)"
                                                                data-history-context-menu-trigger
                                                                class="shrink-0 whitespace-nowrap rounded border px-2.5 py-1 text-[11px] font-semibold transition-colors cursor-pointer {{ str_contains($ref, 'HEAD') ? 'border-cyan-800/50 bg-cyan-900/40 text-cyan-300 hover:bg-cyan-900/50' : (str_contains($ref, '/') ? 'border-[#2a2a42] bg-[#1a1a2e] text-gray-300 hover:bg-[#202035] hover:text-gray-100' : 'border-violet-800/50 bg-violet-900/40 text-violet-300 hover:bg-violet-900/50') }}"
                                                                title="Click to inspect this branch. Double-click to check it out."
                                                            >{{ $graphRefLabel }}</button>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>

                                            <div class="relative border-r border-[#1e1e32] bg-[#171b22]/40">
                                                <div
                                                    class="absolute top-1/2 overflow-hidden rounded-full border-2 border-[#0f1220] -translate-y-1/2 {{ $selectedHistoryType === 'commit' && $selectedCommit === $commit['hash'] ? 'shadow-[0_0_0_2px_rgba(168,85,247,0.45)]' : 'shadow-[0_0_0_1px_rgba(45,212,191,0.35)]' }}"
                                                    :style="avatarStyle('{{ $commit['hash'] }}')"
                                                    title="{{ $commit['author'] }} • {{ $commit['dateHuman'] }}"
                                                >
                                                    <div
                                                        class="flex h-full w-full items-center justify-center text-[10px] font-semibold text-gray-100"
                                                        style="background-color: hsl({{ $commit['avatarHue'] }} 45% 24%);"
                                                    >
                                                        {{ $commit['avatarInitials'] }}
                                                    </div>
                                                    <img
                                                        src="{{ $commit['avatarUrl'] }}"
                                                        alt="{{ $commit['author'] }}"
                                                        class="absolute inset-0 h-full w-full object-cover"
                                                        loading="lazy"
                                                        onerror="this.style.display='none'"
                                                    >
                                                </div>
                                            </div>

                                            <div class="flex min-w-0 flex-col justify-center px-3 py-1.5">
                                                <div class="flex min-w-0 items-center gap-2">
                                                    <div class="truncate text-[0.95rem] text-gray-200">{{ $commit['message'] }}</div>
                                                    @if ($commit['isMerge'])
                                                        <span class="rounded border border-[#2a2a42] px-1.5 py-0.5 uppercase tracking-[0.16em] text-[9px] text-gray-500">Merge</span>
                                                    @endif
                                                    <span class="ml-auto shrink-0 rounded border border-[#313641] bg-[#1c2028] px-1.5 py-0.5 text-[9px] uppercase tracking-[0.14em] text-gray-500">{{ $commit['dateHuman'] }}</span>
                                                </div>
                                                <div class="mt-0.5 flex items-center gap-2 text-[10px] text-gray-600">
                                                    <span class="font-mono">{{ $commit['shortHash'] }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="flex h-full items-center justify-center text-sm text-gray-600">
                                    <div class="text-center">
                                        <div>No commits yet</div>
                                        <div class="mt-1 text-xs text-gray-700">Stage files and create your first commit</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div
                    @mousedown.prevent="startSidebarResize($event)"
                    class="group relative w-1 shrink-0 cursor-col-resize bg-[#1e1e32] transition-colors hover:bg-violet-600"
                >
                    <div class="absolute inset-y-0 -left-1 -right-1 group-hover:bg-transparent"></div>
                </div>

                <aside
                    class="shrink-0 border-l border-[#1e1e32] bg-[#0a0a12] flex flex-col overflow-hidden"
                    :style="'width:' + sidebarWidth + 'px'"
                >
                    @if ($selectedHistoryType)
                        @php
                            $historyAddedCount = collect($selectedHistoryDiffs)->where('status', 'added')->count();
                            $historyDeletedCount = collect($selectedHistoryDiffs)->where('status', 'deleted')->count();
                            $historyMovedCount = collect($selectedHistoryDiffs)->filter(fn (array $diff) => in_array($diff['status'], ['renamed', 'copied'], true))->count();
                        @endphp
                        <div class="border-b border-[#1e1e32] px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    @if ($selectedHistoryType === 'commit' && $selectedCommitData)
                                        <div class="text-sm text-gray-300">
                                            <span class="text-gray-500">commit:</span>
                                            <span class="font-mono text-gray-100">{{ $selectedCommitData['shortHash'] }}</span>
                                        </div>
                                    @elseif ($selectedHistoryType === 'branch' && $selectedBranchData)
                                        <div class="text-sm text-gray-300">
                                            <span class="text-gray-500">branch:</span>
                                            <span class="text-gray-100">{{ $selectedBranchData['name'] }}</span>
                                        </div>
                                    @endif
                                </div>

                                <button
                                    wire:click="clearSelection"
                                    class="shrink-0 rounded border border-[#2a2a42] bg-[#1a1a2e] px-2 py-1 text-xs text-gray-400 transition-colors hover:bg-[#202035] hover:text-gray-200 cursor-pointer"
                                >
                                    Working Tree
                                </button>
                            </div>

                            <div class="mt-3 rounded-lg border border-[#2a2f3b] bg-[#171b23] p-4">
                                @if ($selectedHistoryType === 'commit' && $selectedCommitData)
                                    <div class="text-[1.05rem] leading-snug text-gray-100 whitespace-pre-wrap">{{ $selectedCommitData['message'] }}</div>
                                    <div class="mt-4 flex items-start justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <div class="relative h-11 w-11 overflow-hidden rounded-full border border-[#2a2f3b] bg-[#0f1218]">
                                                <div
                                                    class="flex h-full w-full items-center justify-center text-sm font-semibold text-gray-100"
                                                    style="background-color: hsl({{ $selectedCommitData['avatarHue'] }} 45% 24%);"
                                                >
                                                    {{ $selectedCommitData['avatarInitials'] }}
                                                </div>
                                                <img
                                                    src="{{ $selectedCommitData['avatarUrl'] }}"
                                                    alt="{{ $selectedCommitData['author'] }}"
                                                    class="absolute inset-0 h-full w-full object-cover"
                                                    loading="lazy"
                                                    onerror="this.style.display='none'"
                                                >
                                            </div>
                                            <div>
                                                <div class="text-sm text-gray-200">{{ $selectedCommitData['author'] }}</div>
                                                <div class="text-xs text-gray-500">
                                                    authored {{ \Carbon\Carbon::parse($selectedCommitData['date'])->format('d/m/Y \a\t H:i') }}
                                                </div>
                                            </div>
                                        </div>

                                        @if (count($selectedCommitData['parents']) > 0)
                                            <div class="text-right text-xs text-gray-500">
                                                <div>parent: <span class="font-mono text-gray-300">{{ substr($selectedCommitData['parents'][0], 0, 7) }}</span></div>
                                            </div>
                                        @endif
                                    </div>
                                @elseif ($selectedHistoryType === 'branch' && $selectedBranchData)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded border border-violet-800/50 bg-violet-900/30 px-2.5 py-1 text-sm text-violet-300">{{ $selectedBranchData['name'] }}</span>
                                        <span class="rounded border border-[#2a2a42] bg-[#1a1a2e] px-2 py-1 text-[10px] uppercase tracking-[0.15em] text-gray-500">{{ $selectedBranchData['isRemote'] ? 'Remote' : 'Local' }}</span>
                                    </div>
                                    <div class="mt-3 text-sm text-gray-300">
                                        Showing changes relative to {{ $isDetached ? 'HEAD' : ($currentBranch ?? 'HEAD') }}
                                    </div>
                                @endif
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
                                <span class="text-gray-300">{{ count($selectedHistoryDiffs) }} changed file{{ count($selectedHistoryDiffs) === 1 ? '' : 's' }}</span>
                                @if ($historyAddedCount > 0)
                                    <span class="text-teal-400">+ {{ $historyAddedCount }} added</span>
                                @endif
                                @if ($historyDeletedCount > 0)
                                    <span class="text-red-400">- {{ $historyDeletedCount }} deleted</span>
                                @endif
                                @if ($historyMovedCount > 0)
                                    <span class="text-cyan-400">{{ $historyMovedCount }} moved</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex-1 min-h-0 overflow-y-auto px-4 py-3">
                            <div class="mb-3 flex items-center justify-between text-xs text-gray-500">
                                <span>{{ count($selectedHistoryDiffs) }} changed file{{ count($selectedHistoryDiffs) === 1 ? '' : 's' }}</span>
                                <span>Click a file to open its diff</span>
                            </div>

                            <div class="space-y-1">
                                @forelse ($selectedHistoryDiffs as $diff)
                                    @php
                                        $historyStatusColor = match ($diff['status']) {
                                            'added' => 'text-teal-400',
                                            'deleted' => 'text-red-400',
                                            'renamed', 'copied' => 'text-cyan-400',
                                            default => 'text-violet-300',
                                        };
                                    @endphp

                                    <button
                                        wire:click="selectHistoryFile('{{ $diff['path'] }}')"
                                        class="flex w-full items-center gap-3 rounded px-2 py-2 text-left transition-colors cursor-pointer {{ $selectedFile === $diff['path'] ? 'bg-violet-900/20' : 'hover:bg-[#1a1a2e]/50' }}"
                                    >
                                        <span class="w-3 shrink-0 text-center text-[10px] font-semibold {{ $historyStatusColor }}">
                                            {{ strtoupper(substr($diff['status'], 0, 1)) }}
                                        </span>

                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-xs {{ $selectedFile === $diff['path'] ? 'text-gray-100' : 'text-gray-300' }}">{{ $diff['path'] }}</div>
                                            @if ($diff['oldPath'])
                                                <div class="truncate text-[10px] text-gray-600">&#8592; {{ $diff['oldPath'] }}</div>
                                            @endif
                                        </div>

                                        <div class="shrink-0 text-right text-[10px]">
                                            @if ($diff['additions'] > 0)
                                                <span class="text-teal-400">+{{ $diff['additions'] }}</span>
                                            @endif
                                            @if ($diff['deletions'] > 0)
                                                <span class="ml-1 text-red-500">-{{ $diff['deletions'] }}</span>
                                            @endif
                                        </div>
                                    </button>
                                @empty
                                    <div class="rounded border border-dashed border-[#1e1e32] px-3 py-4 text-center text-xs italic text-gray-600">
                                        No file changes found for this {{ $selectedHistoryType }}
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div class="border-t border-[#1e1e32] px-4 py-3 text-[10px] text-gray-600">
                            Double-click the selected {{ $selectedHistoryType }} to check it out.
                        </div>
                    @else
                        <div class="border-b border-[#1e1e32] px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 text-sm text-gray-100">
                                        <span>{{ $totalFileChanges }} file change{{ $totalFileChanges === 1 ? '' : 's' }} on</span>
                                        <span class="truncate rounded border border-cyan-800/40 bg-cyan-900/30 px-2 py-0.5 text-xs text-cyan-200">
                                            {{ $isDetached ? 'Detached HEAD' : ($currentBranch ?? 'Unknown') }}
                                        </span>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-600">
                                        {{ $upstream ?: 'No upstream configured' }}
                                    </div>
                                </div>
                                @if ($unstagedTotal > 0)
                                    <button
                                        wire:click="stageAll"
                                        class="rounded border border-teal-700/40 px-2 py-1 text-[10px] font-medium text-teal-300 transition-colors hover:bg-teal-900/20 cursor-pointer"
                                    >
                                        Stage All
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="flex-1 min-h-0 overflow-y-auto">
                            <div class="border-b border-[#1e1e32] px-4 py-3">
                                <div class="mb-2 flex items-center justify-between">
                                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">
                                        Unstaged Files ({{ $unstagedTotal }})
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    @forelse ($conflictedFiles as $file)
                                        <div
                                            wire:click="selectFile('{{ $file['path'] }}')"
                                            class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-xs transition-colors hover:bg-[#1a1a2e]/50 {{ $selectedFile === $file['path'] && ! $selectedFileStaged ? 'bg-violet-900/20' : '' }}"
                                        >
                                            <span class="w-2 shrink-0 text-red-500">!</span>
                                            <span class="min-w-0 flex-1 truncate text-red-400">{{ $file['path'] }}</span>
                                        </div>
                                    @empty
                                    @endforelse

                                    @foreach ($unstagedFiles as $file)
                                        <div
                                            wire:click="selectFile('{{ $file['path'] }}')"
                                            class="group flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-xs transition-colors hover:bg-[#1a1a2e]/50 {{ $selectedFile === $file['path'] && ! $selectedFileStaged ? 'bg-violet-900/20' : '' }}"
                                        >
                                            <span class="w-2 shrink-0 text-cyan-500">{{ substr($file['workStatus'], 0, 1) }}</span>
                                            <span class="min-w-0 flex-1 truncate text-gray-400">{{ $file['path'] }}</span>
                                            <div class="ml-auto flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                <button
                                                    wire:click.stop="stageFile('{{ $file['path'] }}')"
                                                    class="rounded p-0.5 text-gray-600 hover:text-violet-400 cursor-pointer"
                                                    title="Stage"
                                                >
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                                    </svg>
                                                </button>
                                                <button
                                                    wire:click.stop="discardFile('{{ $file['path'] }}')"
                                                    wire:confirm="Discard changes to {{ $file['path'] }}?"
                                                    class="rounded p-0.5 text-gray-600 hover:text-red-400 cursor-pointer"
                                                    title="Discard"
                                                >
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach

                                    @foreach ($untrackedFiles as $file)
                                        <div
                                            wire:click="selectFile('{{ $file['path'] }}')"
                                            class="group flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-xs transition-colors hover:bg-[#1a1a2e]/50 {{ $selectedFile === $file['path'] && ! $selectedFileStaged ? 'bg-violet-900/20' : '' }}"
                                        >
                                            <span class="w-2 shrink-0 text-gray-600">?</span>
                                            <span class="min-w-0 flex-1 truncate text-gray-500">{{ $file['path'] }}</span>
                                            <button
                                                wire:click.stop="stageFile('{{ $file['path'] }}')"
                                                class="ml-auto rounded p-0.5 opacity-0 text-gray-600 transition-opacity group-hover:opacity-100 hover:text-violet-400 cursor-pointer"
                                                title="Stage"
                                            >
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                                </svg>
                                            </button>
                                        </div>
                                    @endforeach

                                    @if ($unstagedTotal === 0)
                                        <div class="rounded border border-dashed border-[#1e1e32] px-3 py-4 text-center text-xs italic text-gray-600">
                                            No unstaged files
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="px-4 py-3">
                                <div class="mb-2 flex items-center justify-between">
                                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">
                                        Staged Files ({{ count($stagedFiles) }})
                                    </div>
                                    @if (count($stagedFiles) > 0)
                                        <button
                                            wire:click="unstageAll"
                                            class="text-[10px] text-gray-600 transition-colors hover:text-gray-400 cursor-pointer"
                                        >
                                            Unstage all
                                        </button>
                                    @endif
                                </div>

                                <div class="space-y-1">
                                    @forelse ($stagedFiles as $file)
                                        <div
                                            wire:click="selectFile('{{ $file['path'] }}', true)"
                                            class="group flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-xs transition-colors hover:bg-[#1a1a2e]/50 {{ $selectedFile === $file['path'] && $selectedFileStaged ? 'bg-violet-900/20' : '' }}"
                                        >
                                            <span class="w-2 shrink-0 text-teal-400">{{ substr($file['indexStatus'], 0, 1) }}</span>
                                            <span class="min-w-0 flex-1 truncate text-gray-400">{{ $file['path'] }}</span>
                                            <button
                                                wire:click.stop="unstageFile('{{ $file['path'] }}')"
                                                class="ml-auto rounded p-0.5 opacity-0 text-gray-600 transition-opacity group-hover:opacity-100 hover:text-gray-300 cursor-pointer"
                                                title="Unstage"
                                            >
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/>
                                                </svg>
                                            </button>
                                        </div>
                                    @empty
                                        <div class="rounded border border-dashed border-[#1e1e32] px-3 py-4 text-center text-xs italic text-gray-600">
                                            Nothing staged
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-[#1e1e32] p-3">
                            <div class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">Commit</div>
                            <textarea
                                wire:model="commitMessage"
                                placeholder="Commit message..."
                                rows="4"
                                class="w-full resize-none rounded border border-[#2a2a42] bg-[#11111b] px-3 py-2 text-sm text-gray-200 placeholder:text-gray-600 focus:border-violet-600 focus:outline-none"
                            ></textarea>
                            <button
                                wire:click="commit"
                                wire:loading.attr="disabled"
                                @if (count($stagedFiles) === 0) disabled @endif
                                class="mt-3 w-full rounded border px-3 py-2 text-sm font-medium transition-colors {{ count($stagedFiles) > 0 ? 'border-violet-700/40 bg-violet-600 text-white hover:bg-violet-500 cursor-pointer' : 'border-[#2a2a42] bg-[#141420] text-gray-600 cursor-not-allowed' }}"
                            >
                                @if (count($stagedFiles) > 0)
                                    <span wire:loading.remove wire:target="commit">Commit {{ count($stagedFiles) }} file{{ count($stagedFiles) === 1 ? '' : 's' }}</span>
                                    <span wire:loading wire:target="commit">Committing...</span>
                                @else
                                    <span>Stage changes to commit</span>
                                @endif
                            </button>
                        </div>
                    @endif
                </aside>
            </div>
        @endif
    </div>

    @if ($showContextMenu && $contextMenuTarget)
        <div
            x-cloak
            class="fixed z-50 w-64 overflow-hidden rounded-lg border border-[#2a2a42] bg-[#11111b] shadow-2xl shadow-black/50"
            style="left: {{ $contextMenuX }}px; top: {{ $contextMenuY }}px;"
            data-context-menu-panel
        >
            <div class="border-b border-[#1e1e32] px-3 py-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-gray-500">
                Actions
            </div>

            <div class="py-1.5">
                <button
                    x-on:click="openReferenceSection('local'); $wire.createBranchFromContextMenu()"
                    class="flex w-full items-center justify-between px-3 py-2 text-left text-xs text-gray-300 transition-colors hover:bg-[#1a1a2e] hover:text-gray-100"
                >
                    <span>Create new branch here</span>
                </button>

                @if (($contextMenuTarget['type'] ?? null) === 'commit')
                    <button
                        wire:click="revertContextMenuCommit"
                        class="flex w-full items-center justify-between px-3 py-2 text-left text-xs text-gray-300 transition-colors hover:bg-[#1a1a2e] hover:text-gray-100 cursor-pointer"
                    >
                        <span>Revert Commit</span>
                    </button>
                @endif

                @if (($contextMenuTarget['type'] ?? null) === 'branch')
                    <button
                        wire:click="deleteContextMenuBranchAction"
                        wire:confirm="Delete branch '{{ $contextMenuTarget['displayName'] ?? 'branch' }}'?"
                        @if ($contextMenuTarget['isCurrent'] ?? false) disabled @endif
                        class="flex w-full items-center justify-between px-3 py-2 text-left text-xs transition-colors {{ ($contextMenuTarget['isCurrent'] ?? false) ? 'cursor-not-allowed text-gray-600' : 'cursor-pointer text-gray-300 hover:bg-[#1a1a2e] hover:text-red-300' }}"
                    >
                        <span>Delete {{ $contextMenuTarget['displayName'] ?? 'branch' }}</span>
                    </button>

                    @if ($contextMenuTarget['hasRemotePair'] ?? false)
                        <button
                            wire:click="deleteContextMenuBranchAndRemoteAction"
                            wire:confirm="Delete '{{ $contextMenuTarget['localName'] ?? 'branch' }}' and '{{ $contextMenuTarget['remoteName'] ?? 'remote' }}'?"
                            @if ($contextMenuTarget['isCurrent'] ?? false) disabled @endif
                            class="flex w-full items-center justify-between px-3 py-2 text-left text-xs transition-colors {{ ($contextMenuTarget['isCurrent'] ?? false) ? 'cursor-not-allowed text-gray-600' : 'cursor-pointer text-gray-300 hover:bg-[#1a1a2e] hover:text-red-300' }}"
                        >
                            <span>Delete {{ $contextMenuTarget['localName'] ?? 'branch' }} and {{ $contextMenuTarget['remoteName'] ?? 'remote' }}</span>
                        </button>
                    @endif

                    <button
                        x-on:click="navigator.clipboard.writeText(@js($contextMenuTarget['displayName'] ?? '')); $dispatch('toast', { message: 'Branch name copied', type: 'success' }); $wire.closeContextMenu()"
                        class="flex w-full items-center justify-between px-3 py-2 text-left text-xs text-gray-300 transition-colors hover:bg-[#1a1a2e] hover:text-gray-100"
                    >
                        <span>Copy branch name</span>
                    </button>
                @endif

                <button
                    x-on:click="openReferenceSection('tags'); $wire.createTagFromContextMenu(false)"
                    class="flex w-full items-center justify-between px-3 py-2 text-left text-xs text-gray-300 transition-colors hover:bg-[#1a1a2e] hover:text-gray-100"
                >
                    <span>Create tag here</span>
                </button>

                <button
                    x-on:click="openReferenceSection('tags'); $wire.createTagFromContextMenu(true)"
                    class="flex w-full items-center justify-between px-3 py-2 text-left text-xs text-gray-300 transition-colors hover:bg-[#1a1a2e] hover:text-gray-100"
                >
                    <span>Create annotated tag here</span>
                </button>
            </div>
        </div>
    @endif
</div>
