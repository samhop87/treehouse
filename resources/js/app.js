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

    // Resizable panel splitter (vertical)
    Alpine.data('resizablePanel', () => ({
        panelHeight: 256, // default matches h-64 (16rem = 256px)
        minHeight: 120,
        maxHeight: 600,
        dragging: false,
        startY: 0,
        startHeight: 0,

        startResize(event) {
            this.dragging = true;
            this.startY = event.clientY;
            this.startHeight = this.panelHeight;

            const onMouseMove = (e) => {
                if (!this.dragging) return;
                const delta = this.startY - e.clientY;
                const newHeight = Math.min(
                    this.maxHeight,
                    Math.max(this.minHeight, this.startHeight + delta)
                );
                this.panelHeight = newHeight;
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
            document.body.style.cursor = 'row-resize';
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
