import './bootstrap';
import { computeGraphLayout, drawGraph } from './commit-graph';

// Register Alpine.js components
document.addEventListener('alpine:init', () => {

    Alpine.data('repoView', () => ({
        referenceFilter: '',
        targetedCommitHash: null,
        targetHighlightTimer: null,
        graphRefClickTimer: null,
        branchClickTimer: null,
        layoutStorageKey: 'treehouse.repo-layout-widths.v1',
        filterPanelWidth: 280,
        branchColumnWidth: 192,
        graphColumnWidth: 96,
        detailPanelWidth: 510,
        activeResize: null,
        resizeMouseMoveHandler: null,
        resizeMouseUpHandler: null,
        layoutSyncHandler: null,
        storageSyncHandler: null,
        referenceSections: {
            local: false,
            remote: false,
            stashes: false,
            tags: true,
        },

        init() {
            this.restoreLayoutWidths();

            this.layoutSyncHandler = (event) => {
                this.applyLayoutWidths(event.detail);
            };
            this.storageSyncHandler = (event) => {
                if (event.key !== this.layoutStorageKey || !event.newValue) return;

                try {
                    this.applyLayoutWidths(JSON.parse(event.newValue));
                } catch {
                    // Ignore malformed or unavailable persisted layout data.
                }
            };

            window.addEventListener('treehouse-layout-widths-updated', this.layoutSyncHandler);
            window.addEventListener('storage', this.storageSyncHandler);
        },

        destroy() {
            this.stopWidthResize();
            window.clearTimeout(this.targetHighlightTimer);
            window.clearTimeout(this.graphRefClickTimer);
            window.clearTimeout(this.branchClickTimer);
            window.removeEventListener('treehouse-layout-widths-updated', this.layoutSyncHandler);
            window.removeEventListener('storage', this.storageSyncHandler);
        },

        layoutWidthBounds(property, minimumOverride = null) {
            const bounds = {
                filterPanelWidth: { min: 180, max: 480 },
                branchColumnWidth: { min: 120, max: 480 },
                graphColumnWidth: { min: 72, max: 640 },
                detailPanelWidth: { min: 360, max: 680 },
            }[property];

            if (!bounds) return null;

            const min = Math.max(bounds.min, Number(minimumOverride) || 0);
            let max = Math.max(bounds.max, min);

            if (this.$root) {
                if (property === 'filterPanelWidth') {
                    max = Math.min(max, Math.max(min, this.$root.clientWidth - this.detailPanelWidth - 328));
                }
                if (property === 'detailPanelWidth') {
                    max = Math.min(max, Math.max(min, this.$root.clientWidth - this.filterPanelWidth - 328));
                }
            }

            return { min, max };
        },

        clampLayoutWidth(property, width, minimumOverride = null) {
            const bounds = this.layoutWidthBounds(property, minimumOverride);
            if (!bounds || !Number.isFinite(Number(width))) return null;

            return Math.round(Math.min(bounds.max, Math.max(bounds.min, Number(width))));
        },

        applyLayoutWidths(widths) {
            if (!widths || typeof widths !== 'object') return;

            ['filterPanelWidth', 'branchColumnWidth', 'graphColumnWidth', 'detailPanelWidth'].forEach((property) => {
                const width = this.clampLayoutWidth(property, widths[property]);
                if (width !== null) this[property] = width;
            });
        },

        restoreLayoutWidths() {
            try {
                const savedWidths = window.localStorage.getItem(this.layoutStorageKey);
                if (savedWidths) this.applyLayoutWidths(JSON.parse(savedWidths));
            } catch {
                // Local storage can be unavailable in locked-down browser contexts.
            }
        },

        persistLayoutWidths() {
            const widths = {
                filterPanelWidth: this.filterPanelWidth,
                branchColumnWidth: this.branchColumnWidth,
                graphColumnWidth: this.graphColumnWidth,
                detailPanelWidth: this.detailPanelWidth,
            };

            try {
                window.localStorage.setItem(this.layoutStorageKey, JSON.stringify(widths));
            } catch {
                // Resizing should still work for this session if persistence is unavailable.
            }

            window.dispatchEvent(new CustomEvent('treehouse-layout-widths-updated', { detail: widths }));
        },

        startWidthResize(event, property, direction = 1, minimumOverride = null) {
            const bounds = this.layoutWidthBounds(property, minimumOverride);
            if (!bounds) return;

            this.stopWidthResize();
            this.activeResize = {
                property,
                direction,
                startX: event.clientX,
                startWidth: this[property],
                bounds,
            };

            this.resizeMouseMoveHandler = (moveEvent) => {
                if (!this.activeResize) return;

                const delta = (moveEvent.clientX - this.activeResize.startX) * this.activeResize.direction;
                this[this.activeResize.property] = Math.round(Math.min(
                    this.activeResize.bounds.max,
                    Math.max(this.activeResize.bounds.min, this.activeResize.startWidth + delta)
                ));
            };
            this.resizeMouseUpHandler = () => {
                this.persistLayoutWidths();
                this.stopWidthResize();
            };

            document.addEventListener('mousemove', this.resizeMouseMoveHandler);
            document.addEventListener('mouseup', this.resizeMouseUpHandler);
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        },

        resizeWidthFromKeyboard(event, property, direction = 1, minimumOverride = null) {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;

            event.preventDefault();
            const movement = (event.key === 'ArrowRight' ? 10 : -10) * direction;
            const width = this.clampLayoutWidth(property, this[property] + movement, minimumOverride);
            if (width === null) return;

            this[property] = width;
            this.persistLayoutWidths();
        },

        stopWidthResize() {
            if (this.resizeMouseMoveHandler) {
                document.removeEventListener('mousemove', this.resizeMouseMoveHandler);
            }
            if (this.resizeMouseUpHandler) {
                document.removeEventListener('mouseup', this.resizeMouseUpHandler);
            }

            this.activeResize = null;
            this.resizeMouseMoveHandler = null;
            this.resizeMouseUpHandler = null;
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
        },

        toggleReferenceSection(name) {
            this.referenceSections[name] = !this.referenceSections[name];
        },

        openReferenceSection(name) {
            this.referenceSections[name] = true;
        },

        matchesReference(label) {
            if (!label) return true;

            const query = this.referenceFilter.trim().toLowerCase();

            return query === '' || label.toLowerCase().includes(query);
        },

        handleBranchClick(name) {
            window.clearTimeout(this.branchClickTimer);
            this.branchClickTimer = window.setTimeout(() => {
                this.branchClickTimer = null;
                this.$wire.selectBranch(name);
            }, 250);
        },

        handleBranchDoubleClick(name, isRemote) {
            window.clearTimeout(this.branchClickTimer);
            this.branchClickTimer = null;
            if (isRemote) {
                this.$wire.requestRemoteCheckout(name);
            } else {
                this.$wire.checkoutLocalBranch(name);
            }
        },

        targetCurrentCommit(hash) {
            this.targetedCommitHash = hash;

            this.$nextTick(() => {
                window.requestAnimationFrame(() => {
                    const row = this.$root.querySelector(`[data-commit-hash="${hash}"]`);
                    if (!row) return;

                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    window.clearTimeout(this.targetHighlightTimer);
                    this.targetHighlightTimer = window.setTimeout(() => {
                        this.targetedCommitHash = null;
                    }, 1800);
                });
            });
        },

        handleGraphRefClick(ref) {
            window.clearTimeout(this.graphRefClickTimer);
            this.graphRefClickTimer = window.setTimeout(() => {
                this.graphRefClickTimer = null;
                this.$wire.selectGraphRef(ref);
            }, 250);
        },

        handleGraphRefDoubleClick(ref) {
            window.clearTimeout(this.graphRefClickTimer);
            this.graphRefClickTimer = null;
            this.$wire.checkoutGraphRef(ref);
        },
    }));

    // Toast notification stack
    Alpine.data('toastStack', () => ({
        toasts: [],
        nextId: 0,

        add({ message, type = 'success', duration = 3000 }) {
            const id = this.nextId++;
            const toast = { id, message, type, visible: true };
            this.toasts.push(toast);

            setTimeout(() => {
                toast.visible = false;
                // Remove from DOM after transition
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 200);
            }, duration);
        },
    }));

    // Commit graph renderer
    // Reads commits and selectedCommit reactively from Livewire via $wire
    // so graph stays in sync after Livewire re-renders (wire:ignore.self freezes x-data attrs)
    Alpine.data('commitGraph', () => ({
        graphWidth: 40,
        rowHeight: 40,
        laneWidth: 16,
        graphPadding: 10,
        avatarSize: 24,
        nodes: [],
        nodeMap: {},
        _alive: true,

        init() {
            this.computeAndDraw();

            // Redraw after every Livewire server roundtrip.
            // x-effect with $wire properties doesn't reliably re-fire after
            // DOM morphing, so this acts as a belt-and-suspenders fallback
            // that fires after ALL successful Livewire updates.
            Livewire.hook('commit', ({ succeed }) => {
                succeed(() => {
                    if (!this._alive) return;
                    this.$nextTick(() => this.computeAndDraw());
                });
            });
        },

        destroy() {
            this._alive = false;
        },

        updateGraph() {
            this.$nextTick(() => this.computeAndDraw());
        },

        gridTemplateColumns() {
            return `${this.branchColumnWidth}px ${Math.max(this.graphWidth, this.graphColumnWidth)}px minmax(240px, 1fr)`;
        },

        avatarStyle(hash) {
            const node = this.nodeMap[hash];
            if (!node) return 'opacity: 0;';

            const x = this.graphPadding + (node.lane * this.laneWidth) + (this.laneWidth / 2) - (this.avatarSize / 2);
            return `left: ${x}px; width: ${this.avatarSize}px; height: ${this.avatarSize}px;`;
        },

        computeAndDraw() {
            const commits = this.$wire.get('commits') || [];
            const selectedHash = this.$wire.get('selectedCommit');

            if (commits.length === 0) {
                this.graphWidth = 40;
                this.nodeMap = {};
                return;
            }

            this.nodes = computeGraphLayout(commits);
            this.nodeMap = this.nodes.reduce((carry, node) => {
                carry[node.hash] = node;
                return carry;
            }, {});

            const canvas = this.$refs.graphCanvas;
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const result = drawGraph(ctx, this.nodes, {
                rowHeight: this.rowHeight,
                laneWidth: this.laneWidth,
                nodeRadius: 4,
                padding: this.graphPadding,
                selectedHash: selectedHash,
            });

            if (result) {
                this.graphWidth = result.width;
            }
        },
    }));
});
