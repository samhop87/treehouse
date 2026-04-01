<div
    class="flex h-full min-h-0 overflow-hidden"
    x-data="{ sidebarTab: 'branches' }"
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
>
    <aside class="w-60 shrink-0 border-r border-[#1e1e32] bg-[#06060c] flex flex-col overflow-hidden">
        <div class="border-b border-[#1e1e32] px-3 py-2">
            <div class="text-[10px] uppercase tracking-[0.2em] text-gray-600">References</div>
            <div class="mt-1 text-sm text-gray-400">Double-click a branch to switch to it.</div>
        </div>

        <div class="flex border-b border-[#1e1e32]">
            <button
                @click="sidebarTab = 'branches'"
                :class="sidebarTab === 'branches' ? 'text-gray-200 border-b-2 border-violet-500' : 'text-gray-500 hover:text-gray-400'"
                class="flex-1 px-1 py-2 text-center text-xs font-medium transition-colors cursor-pointer"
            >
                Branches
            </button>
            <button
                @click="sidebarTab = 'tags'"
                :class="sidebarTab === 'tags' ? 'text-gray-200 border-b-2 border-violet-500' : 'text-gray-500 hover:text-gray-400'"
                class="flex-1 px-1 py-2 text-center text-xs font-medium transition-colors cursor-pointer"
            >
                Tags
                @if (count($tags) > 0)
                    <span class="ml-0.5 text-gray-600">{{ count($tags) }}</span>
                @endif
            </button>
            <button
                @click="sidebarTab = 'stashes'"
                :class="sidebarTab === 'stashes' ? 'text-gray-200 border-b-2 border-violet-500' : 'text-gray-500 hover:text-gray-400'"
                class="flex-1 px-1 py-2 text-center text-xs font-medium transition-colors cursor-pointer"
            >
                Stashes
                @if (count($stashes) > 0)
                    <span class="ml-0.5 text-gray-600">{{ count($stashes) }}</span>
                @endif
            </button>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div x-show="sidebarTab === 'branches'" x-cloak x-data="{ branchSection: 'local' }" class="py-2">
                <div class="px-3 pb-2">
                    @if ($showCreateBranch)
                        <div class="rounded-lg border border-[#1e1e32] bg-[#11111b] p-2">
                            <input
                                wire:model="newBranchName"
                                wire:keydown.enter="createBranch"
                                wire:keydown.escape="closeCreateBranch"
                                type="text"
                                placeholder="Branch name..."
                                class="w-full rounded border border-[#2a2a42] bg-[#06060c] px-2 py-1 text-xs text-gray-200 placeholder:text-gray-600 focus:border-violet-600 focus:outline-none"
                                autofocus
                            >
                            <input
                                wire:model="newBranchStartPoint"
                                wire:keydown.enter="createBranch"
                                type="text"
                                placeholder="Start point (optional)"
                                class="mt-1 w-full rounded border border-[#2a2a42] bg-[#06060c] px-2 py-1 text-xs text-gray-200 placeholder:text-gray-600 focus:border-violet-600 focus:outline-none"
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
                                    class="rounded bg-[#1a1a2e] px-2 py-1 text-[10px] text-gray-300 transition-colors hover:bg-[#202035] cursor-pointer"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    @else
                        <button
                            wire:click="openCreateBranch"
                            class="flex w-full items-center gap-1 rounded px-2 py-1 text-xs text-gray-500 transition-colors hover:bg-[#1a1a2e]/50 hover:text-violet-400 cursor-pointer"
                        >
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            New branch
                        </button>
                    @endif
                </div>

                @if ($showMergeConfirm)
                    <div class="mx-3 mb-3 rounded-lg border border-violet-800/40 bg-[#1a1a2e] p-2">
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
                @endif

                @if ($status && $status['hasConflicts'])
                    <div class="mx-3 mb-3 rounded-lg border border-red-800/50 bg-red-900/20 p-2">
                        <div class="mb-1 text-xs text-red-400">Merge conflicts detected</div>
                        <button
                            wire:click="mergeAbort"
                            wire:confirm="Abort the current merge? All merge changes will be lost."
                            class="w-full rounded bg-red-700 px-2 py-1 text-[10px] font-medium text-white transition-colors hover:bg-red-600 cursor-pointer"
                        >
                            Abort Merge
                        </button>
                    </div>
                @endif

                <div class="space-y-3 px-3">
                    <div class="overflow-hidden rounded-lg border border-[#1e1e32] bg-[#0b0b14]">
                        <button
                            type="button"
                            @click="branchSection = branchSection === 'local' ? null : 'local'"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left transition-colors hover:bg-[#141420] cursor-pointer"
                        >
                            <svg
                                class="h-3.5 w-3.5 shrink-0 text-gray-500 transition-transform"
                                :class="branchSection === 'local' ? 'rotate-90 text-gray-300' : ''"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                            <span class="flex-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-purple-400/50">Local</span>
                            <span class="text-xs text-gray-600">{{ count($localBranches) }}</span>
                        </button>

                        <div x-show="branchSection === 'local'" x-collapse class="border-t border-[#1e1e32] p-2">
                            @foreach ($localBranches as $branch)
                                <div
                                    @if (! $branch['isCurrent']) wire:dblclick="checkoutLocalBranch('{{ $branch['name'] }}')" @endif
                                    class="group mb-1 rounded-md border px-2 py-1.5 text-xs transition-colors {{ $branch['isCurrent'] ? 'border-violet-700/30 bg-violet-900/20 text-gray-100' : 'border-transparent text-gray-400 hover:bg-[#1a1a2e]/50 hover:text-gray-300 cursor-pointer' }}"
                                    title="{{ $branch['isCurrent'] ? 'Current branch' : 'Double-click to switch branches' }}"
                                >
                                    <div class="flex items-center gap-1.5">
                                        @if ($branch['isCurrent'])
                                            <span class="h-2 w-2 shrink-0 rounded-full bg-violet-500 shadow-[0_0_6px_rgba(139,92,246,0.5)]"></span>
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
                                                wire:click="openMerge('{{ $branch['name'] }}')"
                                                class="rounded p-0.5 text-gray-600 hover:text-violet-400 cursor-pointer"
                                                title="Merge into {{ $currentBranch }}"
                                            >
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                                </svg>
                                            </button>
                                            <button
                                                wire:click="deleteBranch('{{ $branch['name'] }}')"
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
                            @endforeach
                        </div>
                    </div>

                    @if (count($remoteBranches) > 0)
                        <div class="overflow-hidden rounded-lg border border-[#1e1e32] bg-[#0b0b14]">
                            <button
                                type="button"
                                @click="branchSection = branchSection === 'remote' ? null : 'remote'"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left transition-colors hover:bg-[#141420] cursor-pointer"
                            >
                                <svg
                                    class="h-3.5 w-3.5 shrink-0 text-gray-500 transition-transform"
                                    :class="branchSection === 'remote' ? 'rotate-90 text-gray-300' : ''"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                                <span class="flex-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-purple-400/50">Remote</span>
                                <span class="text-xs text-gray-600">{{ count($remoteBranches) }}</span>
                            </button>

                            <div x-show="branchSection === 'remote'" x-collapse class="border-t border-[#1e1e32] p-2">
                                @foreach ($remoteBranches as $branch)
                                    <div
                                        wire:dblclick="checkoutRemoteBranch('{{ $branch['name'] }}')"
                                        class="mb-1 flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs text-gray-500 transition-colors hover:bg-[#1a1a2e]/50 hover:text-gray-300 cursor-pointer"
                                        title="Double-click to checkout a local tracking branch"
                                    >
                                        <svg class="h-3 w-3 shrink-0 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 3v12m0 0a3 3 0 103 3 3 3 0 00-3-3zm12-6a3 3 0 10-3-3 3 3 0 003 3zm0 0v2a2 2 0 01-2 2H9"/>
                                        </svg>
                                        <span class="truncate">{{ $branch['name'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div x-show="sidebarTab === 'tags'" x-cloak class="py-2">
                <div class="px-3 pb-2">
                    @if ($showCreateTag)
                        <div class="rounded-lg border border-[#1e1e32] bg-[#11111b] p-2">
                            <input
                                wire:model="newTagName"
                                wire:keydown.escape="closeCreateTag"
                                type="text"
                                placeholder="Tag name..."
                                class="w-full rounded border border-[#2a2a42] bg-[#06060c] px-2 py-1 text-xs text-gray-200 placeholder:text-gray-600 focus:border-teal-600 focus:outline-none"
                                autofocus
                            >
                            <input
                                wire:model="newTagRef"
                                type="text"
                                placeholder="Commit (optional)"
                                class="mt-1 w-full rounded border border-[#2a2a42] bg-[#06060c] px-2 py-1 text-xs text-gray-200 placeholder:text-gray-600 focus:border-teal-600 focus:outline-none"
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
                                class="flex flex-1 items-center gap-1 rounded px-2 py-1 text-xs text-gray-500 transition-colors hover:bg-[#1a1a2e]/50 hover:text-teal-400 cursor-pointer"
                            >
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                                New tag
                            </button>
                            @if (count($tags) > 0)
                                <button
                                    wire:click="pushAllTags"
                                    wire:confirm="Push all tags to origin?"
                                    class="rounded px-2 py-1 text-[10px] text-gray-600 transition-colors hover:bg-[#1a1a2e]/50 hover:text-gray-400 cursor-pointer"
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

                @if (count($tags) > 0)
                    @foreach ($tags as $tag)
                        <div class="group flex items-center gap-1.5 px-3 py-1.5 text-xs text-gray-400 transition-colors hover:bg-[#1a1a2e]/50">
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
                    @endforeach
                @else
                    <div class="px-3 py-4 text-center text-xs italic text-gray-600">No tags</div>
                @endif
            </div>

            <div x-show="sidebarTab === 'stashes'" x-cloak class="py-2">
                <div class="px-3 pb-2">
                    @if ($showCreateStash)
                        <div class="rounded-lg border border-[#1e1e32] bg-[#11111b] p-2">
                            <textarea
                                wire:model="newStashMessage"
                                wire:keydown.enter="createStash"
                                wire:keydown.escape="closeCreateStash"
                                placeholder="Stash message (optional)..."
                                rows="2"
                                class="w-full resize-none rounded border border-[#2a2a42] bg-[#06060c] px-2 py-1 text-xs text-gray-200 placeholder:text-gray-600 focus:border-violet-600 focus:outline-none"
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
                            class="flex w-full items-center gap-1 rounded px-2 py-1 text-xs text-gray-500 transition-colors hover:bg-[#1a1a2e]/50 hover:text-violet-400 cursor-pointer"
                        >
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Stash changes
                        </button>
                    @endif
                </div>

                @if (count($stashes) > 0)
                    @foreach ($stashes as $stash)
                        <div class="group px-3 py-1.5 text-xs text-gray-400 transition-colors hover:bg-[#1a1a2e]/50">
                            <div class="flex items-center gap-1.5">
                                <span class="font-mono text-gray-600">{{ $stash['ref'] }}</span>
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
                    @endforeach
                @else
                    <div class="px-3 py-4 text-center text-xs italic text-gray-600">No stashes</div>
                @endif
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

        <div class="flex items-center gap-1 border-b border-[#1e1e32] bg-[#0e0e18] px-3 py-1.5">
            <button
                wire:click="refresh"
                wire:loading.attr="disabled"
                class="flex items-center gap-1 rounded px-2 py-1 text-xs text-gray-400 transition-colors hover:bg-[#1a1a2e] hover:text-gray-200 cursor-pointer"
                title="Refresh (Cmd+R)"
            >
                <svg class="h-3.5 w-3.5" wire:loading.class="animate-spin" wire:target="refresh" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span wire:loading.remove wire:target="refresh">Refresh</span>
                <span wire:loading wire:target="refresh">Loading...</span>
            </button>

            <div class="mx-1 h-4 w-px bg-[#1e1e32]"></div>

            <button
                wire:click="fetchRemote"
                @if ($remoteOperation) disabled @endif
                class="flex items-center gap-1 rounded px-2 py-1 text-xs text-gray-400 transition-colors hover:bg-[#1a1a2e] hover:text-gray-200 disabled:cursor-not-allowed disabled:opacity-40 cursor-pointer"
                title="Fetch from remote (Cmd+Shift+F)"
            >
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <span>{{ $remoteOperation === 'fetch' ? 'Fetching...' : 'Fetch' }}</span>
            </button>

            <button
                wire:click="pullRemote"
                @if ($remoteOperation) disabled @endif
                class="flex items-center gap-1 rounded px-2 py-1 text-xs transition-colors disabled:cursor-not-allowed disabled:opacity-40 cursor-pointer {{ $behind > 0 ? 'text-cyan-400 hover:bg-cyan-900/30 hover:text-cyan-300' : 'text-gray-400 hover:bg-[#1a1a2e] hover:text-gray-200' }}"
                title="Pull from remote (Cmd+Shift+L)"
            >
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
                <span>{{ $remoteOperation === 'pull' ? 'Pulling...' : 'Pull' }}</span>
                @if ($behind > 0 && $remoteOperation !== 'pull')
                    <span class="rounded bg-cyan-900/50 px-1 text-[10px] text-cyan-400">{{ $behind }}</span>
                @endif
            </button>

            <button
                wire:click="pushRemote"
                @if ($remoteOperation) disabled @endif
                class="flex items-center gap-1 rounded px-2 py-1 text-xs transition-colors disabled:cursor-not-allowed disabled:opacity-40 cursor-pointer {{ $ahead > 0 ? 'text-violet-400 hover:bg-violet-900/30 hover:text-violet-300' : 'text-gray-400 hover:bg-[#1a1a2e] hover:text-gray-200' }}"
                title="Push to remote (Cmd+Shift+P)"
            >
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                </svg>
                <span>{{ $remoteOperation === 'push' ? 'Pushing...' : 'Push' }}</span>
                @if ($ahead > 0 && $remoteOperation !== 'push')
                    <span class="rounded bg-violet-900/50 px-1 text-[10px] text-violet-400">{{ $ahead }}</span>
                @endif
            </button>

            <div class="ml-auto flex min-w-0 items-center gap-3">
                @if ($remoteProgress)
                    <span class="truncate text-[10px] text-gray-500">{{ $remoteProgress }}</span>
                @endif

                <div class="min-w-0 text-right">
                    @if ($isDetached)
                        <div class="truncate text-xs font-medium text-cyan-400">Detached HEAD</div>
                        <div class="truncate text-[10px] text-gray-600">{{ $status['headHash'] ?? '' }}</div>
                    @else
                        <div class="truncate text-xs font-medium text-gray-200">{{ $currentBranch }}</div>
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
                    @if ($selectedFile || $selectedCommitData)
                        <div class="flex h-full flex-col overflow-hidden">
                            <div class="border-b border-[#1e1e32] bg-[#0e0e18] px-4 py-3">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0 flex-1">
                                        @if ($selectedCommitData)
                                            <div class="mb-2 text-sm text-gray-200 whitespace-pre-wrap">{{ $selectedCommitData['message'] }}</div>
                                            <div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                                                <span class="text-gray-600">Author</span>
                                                <span class="text-gray-400">{{ $selectedCommitData['author'] }} <span class="text-gray-600">&lt;{{ $selectedCommitData['email'] }}&gt;</span></span>

                                                <span class="text-gray-600">Date</span>
                                                <span class="text-gray-400">{{ \Carbon\Carbon::parse($selectedCommitData['date'])->format('M j, Y g:i A') }} <span class="text-gray-600">({{ $selectedCommitData['dateHuman'] }})</span></span>

                                                <span class="text-gray-600">Commit</span>
                                                <span
                                                    class="cursor-pointer font-mono text-gray-500 transition-colors hover:text-gray-300"
                                                    x-on:click="navigator.clipboard.writeText('{{ $selectedCommitData['hash'] }}'); $dispatch('toast', { message: 'Hash copied', type: 'success' })"
                                                    title="Click to copy hash"
                                                >{{ $selectedCommitData['hash'] }}</span>

                                                @if (count($selectedCommitData['parents']) > 0)
                                                    <span class="text-gray-600">{{ count($selectedCommitData['parents']) > 1 ? 'Parents' : 'Parent' }}</span>
                                                    <span class="flex items-center gap-1.5 font-mono text-gray-500">
                                                        @foreach ($selectedCommitData['parents'] as $parent)
                                                            <span
                                                                class="cursor-pointer transition-colors hover:text-gray-300"
                                                                x-on:click="navigator.clipboard.writeText('{{ $parent }}'); $dispatch('toast', { message: 'Parent hash copied', type: 'success' })"
                                                                title="Click to copy parent hash"
                                                            >{{ substr($parent, 0, 7) }}</span>
                                                        @endforeach
                                                    </span>
                                                @endif

                                                @if (count($selectedCommitData['refs']) > 0)
                                                    <span class="text-gray-600">Refs</span>
                                                    <div class="flex flex-wrap items-center gap-1">
                                                        @foreach ($selectedCommitData['refs'] as $ref)
                                                            @if (str_starts_with($ref, 'tag:'))
                                                                <span class="rounded border border-teal-800/50 bg-teal-900/40 px-1.5 py-0.5 text-[10px] text-teal-400">{{ trim(str_replace('tag:', '', $ref)) }}</span>
                                                            @elseif (str_contains($ref, 'HEAD'))
                                                                <span class="rounded border border-cyan-800/50 bg-cyan-900/40 px-1.5 py-0.5 text-[10px] text-cyan-400">{{ $ref }}</span>
                                                            @elseif (str_contains($ref, '/'))
                                                                <span class="rounded border border-[#2a2a42] bg-[#1a1a2e] px-1.5 py-0.5 text-[10px] text-gray-400">{{ $ref }}</span>
                                                            @else
                                                                <span class="rounded border border-violet-800/50 bg-violet-900/40 px-1.5 py-0.5 text-[10px] text-violet-400">{{ $ref }}</span>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <div class="text-[10px] uppercase tracking-[0.2em] text-gray-600">
                                                {{ $selectedFileStaged ? 'Staged Diff' : 'Working Tree Diff' }}
                                            </div>
                                            <div class="mt-1 truncate text-sm font-medium text-gray-200">{{ $selectedFile }}</div>
                                        @endif
                                    </div>

                                    <button
                                        wire:click="clearSelection"
                                        class="shrink-0 rounded border border-[#2a2a42] bg-[#1a1a2e] px-2 py-1 text-xs text-gray-400 transition-colors hover:bg-[#202035] hover:text-gray-200 cursor-pointer"
                                    >
                                        Back to history
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
                                @elseif ($selectedFile)
                                    <div class="flex h-full items-center justify-center text-sm text-gray-600">No diff available</div>
                                @else
                                    <div class="flex h-full items-center justify-center text-sm text-gray-600">No changes in this commit</div>
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
                                <div class="relative text-xs font-mono">
                                    <canvas x-ref="graphCanvas" wire:ignore class="absolute top-0 left-0 pointer-events-none"></canvas>

                                    @foreach ($commits as $commit)
                                        <div
                                            wire:click="selectCommit('{{ $commit['hash'] }}')"
                                            class="flex h-7 cursor-pointer items-center transition-colors hover:bg-[#1a1a2e]/30"
                                        >
                                            <div class="shrink-0" :style="'width:' + graphWidth + 'px'"></div>

                                            <div class="flex shrink-0 items-center gap-1 px-1">
                                                @foreach ($commit['refs'] as $ref)
                                                    @if (str_starts_with($ref, 'tag:'))
                                                        <span class="whitespace-nowrap rounded border border-teal-800/50 bg-teal-900/40 px-1.5 py-0.5 text-[10px] text-teal-400">{{ trim(str_replace('tag:', '', $ref)) }}</span>
                                                    @elseif (str_contains($ref, 'HEAD'))
                                                        <span class="whitespace-nowrap rounded border border-cyan-800/50 bg-cyan-900/40 px-1.5 py-0.5 text-[10px] text-cyan-400">{{ $ref }}</span>
                                                    @elseif (str_contains($ref, '/'))
                                                        <span class="whitespace-nowrap rounded border border-[#2a2a42] bg-[#1a1a2e] px-1.5 py-0.5 text-[10px] text-gray-400">{{ $ref }}</span>
                                                    @else
                                                        <span class="whitespace-nowrap rounded border border-violet-800/50 bg-violet-900/40 px-1.5 py-0.5 text-[10px] text-violet-400">{{ $ref }}</span>
                                                    @endif
                                                @endforeach
                                            </div>

                                            <div class="flex-1 truncate px-1 text-gray-300">{{ $commit['message'] }}</div>

                                            <div class="shrink-0 px-2 text-right text-gray-600">
                                                <span>{{ $commit['author'] }}</span>
                                                <span class="ml-2">{{ $commit['dateHuman'] }}</span>
                                            </div>

                                            <div
                                                class="w-16 shrink-0 cursor-pointer pr-3 text-right text-gray-700 transition-colors hover:text-gray-400"
                                                x-on:click.stop="navigator.clipboard.writeText('{{ $commit['hash'] }}'); $dispatch('toast', { message: 'Hash copied', type: 'success' })"
                                                title="Click to copy full hash"
                                            >{{ $commit['shortHash'] }}</div>
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
                    <div class="border-b border-[#1e1e32] px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-sm text-gray-100">
                                    {{ $totalFileChanges }} file change{{ $totalFileChanges === 1 ? '' : 's' }}
                                </div>
                                <div class="text-xs text-gray-600">
                                    {{ $isDetached ? 'Detached HEAD' : 'on ' . ($currentBranch ?? 'Unknown') }}
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
                </aside>
            </div>
        @endif
    </div>
</div>
