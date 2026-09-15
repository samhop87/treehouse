import { notarize } from '@electron/notarize';

export default async (context) => {
    // Only notarize when process is running on a Mac
    if (process.platform !== 'darwin') return;

    // And the current build target is macOS
    if (context.packager.platform.name !== 'mac') return;

    const appleId = process.env.NATIVEPHP_APPLE_ID?.trim();
    const appleIdPassword = process.env.NATIVEPHP_APPLE_ID_PASS?.trim();
    const teamId = process.env.NATIVEPHP_APPLE_TEAM_ID?.trim();

    if (!appleId || !appleIdPassword || !teamId) {
        console.warn(
            'skipping notarizing, NATIVEPHP_APPLE_ID, NATIVEPHP_APPLE_ID_PASS and NATIVEPHP_APPLE_TEAM_ID env variables must be set.',
        );
        return;
    }

    console.log('aftersign hook triggered, start to notarize app.');

    const appId = process.env.NATIVEPHP_APP_ID;

    const { appOutDir } = context;

    const appName = context.packager.appInfo.productFilename;

    await notarize({
        appBundleId: appId,
        appPath: `${appOutDir}/${appName}.app`,
        appleId,
        appleIdPassword,
        teamId,
        tool: 'notarytool',
    });

    console.log(`done notarizing ${appId}.`);
};
