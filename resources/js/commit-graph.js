/**
 * Commit graph layout algorithm.
 *
 * Takes a linear list of commits (newest first) and assigns each commit
 * to a visual lane (column) so that branch/merge topology is visible.
 *
 * Algorithm overview:
 * - Walk commits top-to-bottom (newest to oldest)
 * - Maintain a set of "active lanes" — each lane tracks which commit hash
 *   is expected next (i.e., the parent we're waiting for)
 * - When a commit appears, find its lane (where it was expected)
 * - Route its parents into lanes (reuse the current lane for the first parent,
 *   allocate new lanes for additional parents)
 * - When a lane's expected commit arrives, the lane continues or closes
 *
 * Returns an array of graph nodes with lane assignments and edge info.
 */

/**
 * @typedef {Object} GraphNode
 * @property {string} hash
 * @property {number} lane - Column index (0-based)
 * @property {number} row - Row index (0-based)
 * @property {Array<GraphEdge>} edges - Connections to parent commits
 * @property {number} maxLane - Maximum lane index used at this row
 */

/**
 * @typedef {Object} GraphEdge
 * @property {number} fromLane - Source lane
 * @property {number} fromRow - Source row
 * @property {number} toLane - Destination lane
 * @property {number} toRow - Destination row
 * @property {string} color - Edge color
 */

const LANE_COLORS = [
    '#34d399', // emerald-400
    '#60a5fa', // blue-400
    '#f472b6', // pink-400
    '#a78bfa', // violet-400
    '#fb923c', // orange-400
    '#facc15', // yellow-400
    '#2dd4bf', // teal-400
    '#f87171', // red-400
    '#818cf8', // indigo-400
    '#4ade80', // green-400
    '#c084fc', // purple-400
    '#38bdf8', // sky-400
];

/**
 * Compute the graph layout for a list of commits.
 *
 * @param {Array<{hash: string, parents: string[]}>} commits - Commits, newest first
 * @returns {Array<GraphNode>} Graph nodes with lane/edge info
 */
export function computeGraphLayout(commits) {
    if (!commits || commits.length === 0) return [];

    // activeLanes[i] = hash that lane i is waiting for (null = lane is free)
    const activeLanes = [];
    // Map from hash to row index for quick parent lookup
    const hashToRow = new Map();
    const nodes = [];

    for (let row = 0; row < commits.length; row++) {
        const commit = commits[row];
        hashToRow.set(commit.hash, row);

        // Find which lane is expecting this commit
        let lane = activeLanes.indexOf(commit.hash);

        if (lane === -1) {
            // This commit wasn't expected in any lane — allocate a new one
            lane = findFreeLane(activeLanes);
            activeLanes[lane] = commit.hash;
        }

        // This lane has now received its expected commit
        activeLanes[lane] = null;

        const edges = [];
        const parents = commit.parents || [];

        if (parents.length >= 1) {
            // First parent continues in the same lane
            activeLanes[lane] = parents[0];

            edges.push({
                fromLane: lane,
                fromRow: row,
                toLane: lane, // tentative — may be updated when parent is placed
                toRow: null,  // filled when we reach the parent
                parentHash: parents[0],
                color: laneColor(lane),
            });
        }

        // Additional parents (merge edges) get their own lanes
        for (let p = 1; p < parents.length; p++) {
            const parentHash = parents[p];

            // Check if this parent is already expected in some lane
            let parentLane = activeLanes.indexOf(parentHash);
            if (parentLane === -1) {
                // Allocate a new lane for this parent
                parentLane = findFreeLane(activeLanes);
                activeLanes[parentLane] = parentHash;
            }

            edges.push({
                fromLane: lane,
                fromRow: row,
                toLane: parentLane,
                toRow: null,
                parentHash: parentHash,
                color: laneColor(parentLane),
            });
        }

        // Compact: close any lanes that have converged
        // (two lanes waiting for the same hash — keep only one)
        compactLanes(activeLanes);

        const maxLane = activeLanes.reduce((max, val, idx) => val !== null ? Math.max(max, idx) : max, lane);

        nodes.push({
            hash: commit.hash,
            lane,
            row,
            edges,
            maxLane,
        });
    }

    // Post-process: resolve edge destination rows and lanes
    for (const node of nodes) {
        for (const edge of node.edges) {
            const parentRow = hashToRow.get(edge.parentHash);
            if (parentRow !== undefined) {
                edge.toRow = parentRow;
                edge.toLane = nodes[parentRow].lane;
            } else {
                // Parent is outside our commit window — draw to bottom
                edge.toRow = commits.length;
                // Keep toLane as-is
            }
        }
    }

    return nodes;
}

function findFreeLane(activeLanes) {
    for (let i = 0; i < activeLanes.length; i++) {
        if (activeLanes[i] === null || activeLanes[i] === undefined) {
            return i;
        }
    }
    activeLanes.push(null);
    return activeLanes.length - 1;
}

function compactLanes(activeLanes) {
    // If two lanes track the same hash, free the higher-index one
    for (let i = 0; i < activeLanes.length; i++) {
        if (activeLanes[i] === null) continue;
        for (let j = i + 1; j < activeLanes.length; j++) {
            if (activeLanes[j] === activeLanes[i]) {
                activeLanes[j] = null;
            }
        }
    }
}

function laneColor(lane) {
    return LANE_COLORS[lane % LANE_COLORS.length];
}

/**
 * Draw the commit graph onto a canvas.
 *
 * @param {CanvasRenderingContext2D} ctx
 * @param {Array<GraphNode>} nodes
 * @param {Object} opts
 * @param {number} opts.rowHeight - Height of each row in pixels
 * @param {number} opts.laneWidth - Width of each lane in pixels
 * @param {number} opts.nodeRadius - Radius of commit circles
 * @param {number} opts.padding - Left padding
 * @param {string|null} opts.selectedHash - Currently selected commit hash
 */
export function drawGraph(ctx, nodes, opts = {}) {
    const {
        rowHeight = 28,
        laneWidth = 16,
        nodeRadius = 4,
        padding = 12,
        selectedHash = null,
    } = opts;

    if (!nodes || nodes.length === 0) return;

    // Compute canvas dimensions
    const maxLane = nodes.reduce((max, n) => Math.max(max, n.maxLane), 0);
    const width = padding + (maxLane + 1) * laneWidth + padding;
    const height = nodes.length * rowHeight;

    ctx.canvas.width = width * window.devicePixelRatio;
    ctx.canvas.height = height * window.devicePixelRatio;
    ctx.canvas.style.width = width + 'px';
    ctx.canvas.style.height = height + 'px';
    ctx.scale(window.devicePixelRatio, window.devicePixelRatio);

    ctx.clearRect(0, 0, width, height);

    const cx = (lane) => padding + lane * laneWidth + laneWidth / 2;
    const cy = (row) => row * rowHeight + rowHeight / 2;

    // Draw edges first (behind nodes)
    ctx.lineWidth = 1.5;
    for (const node of nodes) {
        for (const edge of node.edges) {
            if (edge.toRow === null) continue;

            const x1 = cx(edge.fromLane);
            const y1 = cy(edge.fromRow);
            const x2 = cx(edge.toLane);
            const y2 = cy(edge.toRow);

            ctx.beginPath();
            ctx.strokeStyle = edge.color;

            if (edge.fromLane === edge.toLane) {
                // Straight line
                ctx.moveTo(x1, y1);
                ctx.lineTo(x2, y2);
            } else {
                // Curved line — bezier from source to destination
                const midY = y1 + rowHeight * 0.7;
                ctx.moveTo(x1, y1);
                ctx.bezierCurveTo(x1, midY, x2, midY, x2, y2);
            }

            ctx.stroke();
        }
    }

    // Draw nodes
    for (const node of nodes) {
        const x = cx(node.lane);
        const y = cy(node.row);
        const color = laneColor(node.lane);
        const isSelected = node.hash === selectedHash;

        ctx.beginPath();
        ctx.arc(x, y, isSelected ? nodeRadius + 1.5 : nodeRadius, 0, Math.PI * 2);
        ctx.fillStyle = color;
        ctx.fill();

        if (isSelected) {
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 2;
            ctx.stroke();
        }
    }

    // Return the computed width so the parent can size the graph column
    return { width, height };
}
