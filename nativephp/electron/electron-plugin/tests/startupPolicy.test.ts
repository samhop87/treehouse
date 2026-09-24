import { describe, expect, it } from 'vitest';
import { needsOptimization, startupCacheKey, startupCachePaths } from '../src/server/startupPolicy';

describe('startup cache policy', () => {
    it('uses a fresh cache when the same version moves or is rebuilt', () => {
        const mounted = startupCacheKey('0.1.0', '/Volumes/Treehouse/Treehouse.app/build/app', 1000);
        const installed = startupCacheKey('0.1.0', '/Applications/Treehouse.app/build/app', 1000);
        const rebuilt = startupCacheKey('0.1.0', '/Applications/Treehouse.app/build/app', 2000);

        expect(startupCacheKey('0.1.0', '/Applications/Treehouse.app/build/app', 1000)).toBe(installed);
        expect(installed).not.toBe(mounted);
        expect(rebuilt).not.toBe(installed);
        expect(startupCachePaths('/cache', installed, '/views').config)
            .not.toBe(startupCachePaths('/cache', mounted, '/views').config);
    });

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
