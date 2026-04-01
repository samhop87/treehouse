import './bootstrap';
import { computeGraphLayout, drawGraph } from './commit-graph';

// Register Alpine.js components
document.addEventListener('alpine:init', () => {

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

    // Resizable right sidebar splitter
    Alpine.data('repoLayout', () => ({
        sidebarWidth: 360,
        minSidebarWidth: 300,
        maxSidebarWidth: 520,
        dragging: false,
        startX: 0,
        startWidth: 0,

        startSidebarResize(event) {
            this.dragging = true;
            this.startX = event.clientX;
            this.startWidth = this.sidebarWidth;

            const onMouseMove = (e) => {
                if (!this.dragging) return;
                const delta = e.clientX - this.startX;
                const maxWidth = Math.max(this.minSidebarWidth, Math.min(this.maxSidebarWidth, window.innerWidth - 420));
                const newWidth = Math.min(
                    maxWidth,
                    Math.max(this.minSidebarWidth, this.startWidth - delta)
                );
                this.sidebarWidth = newWidth;
            };

            const onMouseUp = () => {
                this.dragging = false;
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            };

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        },
    }));

    // Commit graph renderer
    // Reads commits and selectedCommit reactively from Livewire via $wire
    // so graph stays in sync after Livewire re-renders (wire:ignore.self freezes x-data attrs)
    Alpine.data('commitGraph', () => ({
        graphWidth: 40,
        nodes: [],
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

        computeAndDraw() {
            const commits = this.$wire.get('commits') || [];
            const selectedHash = this.$wire.get('selectedCommit');

            if (commits.length === 0) {
                this.graphWidth = 40;
                return;
            }

            this.nodes = computeGraphLayout(commits);

            const canvas = this.$refs.graphCanvas;
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const result = drawGraph(ctx, this.nodes, {
                rowHeight: 28,
                laneWidth: 16,
                nodeRadius: 4,
                padding: 12,
                selectedHash: selectedHash,
            });

            if (result) {
                this.graphWidth = result.width;
            }
        },
    }));
});
