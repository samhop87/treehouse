# Releasing Treehouse for Apple Silicon macOS

Treehouse 0.1.1 targets Apple Silicon. The installed application contains PHP and Electron, while Git comes from macOS. On a fresh Mac, install Git once with `xcode-select --install` if Treehouse reports that it is unavailable.

Version 0.1.1 fixes startup after an app build is replaced or moved from a DMG to Applications by rebuilding Laravel's location-dependent cache for that installation.

## Build prerequisites

- Apple Silicon Mac
- PHP 8.2 or newer and Composer
- Node.js 22 or newer and npm
- Apple Command Line Tools
- Dependencies installed with `composer install` and `npm ci`

Run the release gate and build from the repository root:

```sh
./scripts/build-macos-arm64.sh
```

The script clears cached configuration, audits the production PHP lock, runs the PHP suite and production frontend build, audits the complete Electron dependency lock, asks NativePHP to build for `mac arm64`, verifies the disk image container, and writes its SHA-256 checksum. NativePHP strips development files, tests, package caches, debug mode, and credential fields from the application copy. The expected outputs are:

```text
nativephp/electron/dist/Treehouse-0.1.1-arm64.dmg
nativephp/electron/dist/Treehouse-0.1.1-arm64.dmg.sha256
```

## Signing and notarization

A transferable release should be signed with a **Developer ID Application** certificate and notarized by Apple. Install the certificate and private key in the build Mac's login keychain. Export the notarization values only in the release shell:

```sh
export NATIVEPHP_APPLE_ID="your-apple-id@example.com"
export NATIVEPHP_APPLE_ID_PASS="app-specific-password"
export NATIVEPHP_APPLE_TEAM_ID="YOURTEAMID"
./scripts/build-macos-arm64.sh
```

Do not put these values in `.env` or commit them. NativePHP passes them to Electron Builder's notarization hook. The build fails if notarization or stapling fails. Confirm that Electron Builder selected the intended Developer ID identity in the build output.

Mount the resulting DMG and verify the application inside it:

```sh
hdiutil verify nativephp/electron/dist/Treehouse-0.1.1-arm64.dmg
hdiutil attach -nobrowse -readonly nativephp/electron/dist/Treehouse-0.1.1-arm64.dmg
codesign --verify --deep --strict --verbose=2 "/Volumes/Treehouse 0.1.1-arm64/Treehouse.app"
spctl --assess --type execute --verbose=2 "/Volumes/Treehouse 0.1.1-arm64/Treehouse.app"
xcrun stapler validate "/Volumes/Treehouse 0.1.1-arm64/Treehouse.app"
hdiutil detach "/Volumes/Treehouse 0.1.1-arm64"
```

Use the actual mounted volume name shown by `hdiutil attach` if it differs.

## Work-Mac acceptance check

Copy the DMG and its `.sha256` file to the Apple Silicon work Mac. In Terminal, run `shasum -a 256 -c Treehouse-0.1.1-arm64.dmg.sha256` in their containing directory. Then open the DMG, drag Treehouse to Applications, eject the image, and launch Treehouse from Finder.

Verify these workflows against a disposable repository before opening important work repositories:

1. Open an existing repository and confirm branch, upstream, ahead/behind, graph, and working-tree state.
2. Preview and stage new, modified, deleted, renamed, Unicode, spaced, and apostrophe-containing paths; unstage them and confirm the index matches.
3. Commit, fetch, pull, and push. Confirm offline, authentication, and rejected-push failures remain visible and do not show success.
4. Merge and rebase both cleanly and with a conflict. Resolve and stage files, continue, then repeat and abort.
5. Restart Treehouse during a conflicted rebase and confirm the recovery panel returns.
6. Confirm Git authentication uses the work Mac's existing SSH agent or HTTPS credential helper. GitHub login in Treehouse is for repository discovery and does not replace Git transport credentials.

Retain the previous DMG until the new build has passed these checks. Installation and upgrade must not modify any repository.

## Current release limits

- System Git is required; Git is not bundled in 0.1.1.
- Staging works at whole-file level; hunk and line staging are not implemented.
- Rebase supports branch-target rebase and conflict recovery; interactive squash/reorder is not implemented.
- The history graph initially loads the most recent 200 commits. Treehouse can focus on a selected branch, or expand all-ref history in 200-commit batches up to 1,600 commits; checking out another branch returns it to the default view.
- GitHub repository discovery is optional. Local repositories and manual clone URLs work without it.
- NativePHP's secure-source bundle is not installed, so PHP application source is readable inside this beta's app bundle.
- An unsigned development DMG is suitable for local testing only. A work-Mac release must pass the signing and notarization checks above.

## Runtime maintenance

Treehouse owns the published NativePHP Electron project in `nativephp/electron` so its runtime versions are explicit. Version 0.1.1 uses Livewire 4.4.4, NativePHP Desktop 2.3.1, NativePHP PHP binaries 1.2.0, and Electron 41.10.3. The committed production Composer lock and complete npm lock currently report zero advisories.

When upgrading NativePHP, review its published Electron template before replacing this directory. Running `native:install --publish` overwrites the project. Reapply intentional runtime changes—including Treehouse's static launch splash, combined startup command, and versioned Application Support caches—run the full npm audit, and repeat the DMG launch check before accepting an update.
