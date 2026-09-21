import { describe, expect, it } from 'vitest';
import { needsOptimization, startupCachePaths } from '../src/server/startupPolicy';

describe('startup cache policy', () => {
    it('keeps each application version in its own cache directory', () => {
        const paths = startupCachePaths('/application-support/bootstrap/cache', '1.0.0/beta', '/views');

        expect(paths.directory).toBe('/application-support/bootstrap/cache/1.0.0_beta');
        expect(paths.config).toBe('/application-support/bootstrap/cache/1.0.0_beta/config.php');
    });

    it('optimizes only when a production cache is missing or belongs to another version', () => {
        const paths = startupCachePaths('/cache', '1.0.0', '/views');
        const completeCache = new Set([
            paths.services,
            paths.packages,
            paths.config,
            paths.routes,
            paths.events,
        ]);
        const store = { get: () => '1.0.0' };

        expect(needsOptimization(store, '1.0.0', paths, (path) => completeCache.has(path), false)).toBe(false);
        expect(needsOptimization(store, '1.0.1', paths, (path) => completeCache.has(path), false)).toBe(true);
        expect(needsOptimization(store, '1.0.0', paths, (path) => path !== paths.events, false)).toBe(true);
        expect(needsOptimization(store, '1.0.0', paths, () => false, true)).toBe(false);
    });
});
