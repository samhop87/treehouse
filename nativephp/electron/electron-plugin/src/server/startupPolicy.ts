import { createHash } from 'crypto';
import { join } from 'path';

export interface VersionStore {
    get(key: string): unknown;
}

export interface StartupCachePaths {
    directory: string;
    services: string;
    packages: string;
    config: string;
    routes: string;
    events: string;
    views: string;
}

export const requiredStartupCachePaths = (paths: StartupCachePaths): string[] => [
    paths.services,
    paths.packages,
    paths.config,
    paths.routes,
    paths.events,
];

export function startupCacheKey(appVersion: string, appPath: string, appModifiedAtMs: number): string {
    // Laravel's cached configuration contains absolute paths. A build moved from
    // a DMG to Applications, or replaced at the same path without a version bump,
    // must never load the previous build's configuration during bootstrap.
    const installation = createHash('sha256')
        .update(appPath)
        .update('\0')
        .update(String(appModifiedAtMs))
        .digest('hex')
        .slice(0, 12);

    return `${appVersion}-${installation}`;
}

export function startupCachePaths(cacheRoot: string, appVersion: string, viewsPath: string): StartupCachePaths {
    const safeVersion = appVersion.replace(/[^A-Za-z0-9._-]/g, '_') || 'unknown';
    const directory = join(cacheRoot, safeVersion);

    return {
        directory,
        services: join(directory, 'services.php'),
        packages: join(directory, 'packages.php'),
        config: join(directory, 'config.php'),
        routes: join(directory, 'routes-v7.php'),
        events: join(directory, 'events.php'),
        views: viewsPath,
    };
}

export function needsOptimization(
    store: VersionStore,
    appVersion: string,
    paths: StartupCachePaths,
    exists: (path: string) => boolean,
    isDevelopment: boolean,
): boolean {
    if (isDevelopment) {
        return false;
    }

    if (store.get('optimized_version') !== appVersion) {
        return true;
    }

    return requiredStartupCachePaths(paths).some((path) => !exists(path));
}
