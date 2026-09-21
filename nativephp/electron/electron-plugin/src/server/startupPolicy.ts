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
