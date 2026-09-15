Treehouse project review and macOS release plan — 14 September 2026

Treehouse is a working GitKraken-style MVP, with a recognisable interface and a useful core of Git operations. It is not yet a dependable substitute for your work use: rebase is missing, conflict recovery is incomplete, and several errors can be hidden or reported as success. The desktop architecture is already in place, so distributing it does not require a rewrite.

**Implementation update — 14 September 2026:** the first release slice is now implemented on `codex/treehouse-macos-release`. It adds branch rebase with continue/abort/skip recovery, persistent Git-operation detection, accurate native clone/remote failure reporting, safe filename and rename handling, untracked-file previews, exact paired branch deletion, missing-Git and missing-GitHub-configuration handling, explicit merge-style pull behavior, OS-backed secure storage for new GitHub tokens, release metadata, and a 1024px Treehouse icon. The full suite now passes **242 tests**. Release scripts and handoff documentation live in `scripts/` and `docs/RELEASING.md`.

The first beta intentionally uses the macOS system Git and detects it on launch. Bundling a separate Git toolchain remains a later hardening step. The runtime was upgraded to Livewire 4.4.4 and NativePHP Desktop 2.3.1 with PHP binaries 1.2.0, and its published Electron project now locks Electron 41.10.3. The production Composer audit and both npm audits report zero advisories. An ARM64 development DMG was built, its container verified, and its packaged app launch-tested; it detected Git 2.50.1. Developer ID signing, notarization, and validation on the separate work Mac remain open because this development Mac has no signing identity installed.

The target is your Apple Silicon work Mac. Your priorities are branch visibility, reviewing and staging changes, committing, pushing, pulling, merging and rebasing. Repository hosting, authentication method, work macOS version and availability of a Developer ID signing identity remain to be confirmed during implementation. Those details do not prevent this plan.

The comparison baseline is GitKraken Desktop 12.4.1, released 2 September 2026. Its recent additions include worktree tab groups and Codex agent status/approval controls. These are outside your stated requirements. [Current release notes](https://help.gitkraken.com/gitkraken-desktop/current/)

**What is here and what was verified**

The repository was clean at the start of this review. There are nine commits, from 31 March to 7 April 2026. Recent work added multi-repository tabs, branch and commit inspection, context menus, and repository actions in the workspace header. HEAD is `0605166`.

At the start of the review, the locked application dependencies were Laravel 12.56.0, Livewire 4.2.2, NativePHP Desktop 2.1.1 and NativePHP PHP binaries 1.1.1. NativePHP used Electron; the installed desktop template specified Electron `^38.0.0`. Git commands use the external `git` executable. The README still described Livewire 3 and 211 tests, so it was behind the implementation.

Validation completed:

- `php artisan test --compact`: **230 passed, 605 assertions**, 3.35 seconds.
- `npm run build`: successful production assets, Vite 7.3.1.
- Browser smoke inspection using a separate temporary SQLite database: repository view loaded; commit selection displayed metadata and changed files. At a 1280-pixel viewport, the large staging panel leaves commit messages noticeably truncated.
- Reproduced a `TypeError` when constructing `GitHubAuthService` with a missing client ID.

The initial review did not build or launch a packaged application, sign or notarize a release, test on the work Mac, or perform live remote mutations. The implementation update above records the work performed after that baseline.

**How close it is to GitKraken**

The layout follows the familiar references/graph/commit-panel arrangement. GitKraken also provides broader history tools, recovery controls, hosting integrations, worktrees and an embedded terminal. [Interface guide](https://help.gitkraken.com/gitkraken-desktop/interface/)

| Workflow | Treehouse today | Assessment for your use |
|---|---|---|
| See branches and repository state | Canvas graph, local/remote refs, current branch, upstream and ahead/behind data, ref filtering, commit and branch inspection | Good foundation; history limited to 200 commits with no paging; layout needs better message space |
| Review and stage changes | Staged/unstaged lists, whole-file stage/unstage/discard, unified diffs, commit form | Core workflow present; new-file preview, unusual filenames and rename handling need fixes |
| Fine-grained staging | No line or hunk actions | Significant gap if you split edits within a file; GitKraken supports both. [Staging documentation](https://help.gitkraken.com/gitkraken-desktop/staging/) |
| Push, pull and fetch | Implemented, including native background processes and first push with upstream | Present but native failure reporting is a release blocker |
| Merge | Merge and abort operations, conflict detection/listing | Clean merges supported; conflict resolution/recovery incomplete |
| Rebase | No explicit rebase workflow; bare `git pull` may inherit a user's configured rebase policy | Missing for your stated workflow. GitKraken offers branch rebase and interactive history editing. [Branching guide](https://help.gitkraken.com/gitkraken-desktop/branching-and-merging/), [interactive rebase](https://help.gitkraken.com/gitkraken-desktop/interactive-rebase/) |
| Other local operations | Branch creation/checkout/deletion, tags, stashes and commit revert | Useful coverage; amend exists in service code but has no exposed UI |
| Multiple repositories | Tabs and recent repositories | Useful; open tab sessions are not restored after restart |
| Authentication and discovery | GitHub device login and owner-repository picker; manual clone URL | API login is not connected to Git transport credentials; work organization repos can be omitted |
| Advanced/product-wide parity | No cherry-pick, interactive rebase, undo/reflog UI, blame/file history, terminal, dedicated worktree/submodule/LFS UI, PR/issue integrations or agent workflows | Far from full product parity; most can be deferred for your use. Existing system Git configuration can still enable some underlying behavior. GitKraken's file history/blame tools are documented [here](https://help.gitkraken.com/gitkraken-desktop/diff/). |

For your needs, the interface and ordinary operations are substantially present. Reliability and rebase are the decisive gaps. A single percentage would obscure that distinction: a visible Push button is not equivalent to a trustworthy push workflow.

**Issues to address before using a release with work repositories**

1. **P1 — Packaged startup can fail without GitHub configuration.** [Build cleanup](/Users/samhopkinson/webroot/treehouse/config/nativephp.php:61) removes `GITHUB_*`, including the public client ID. [Services configuration](/Users/samhopkinson/webroot/treehouse/config/services.php:39) then supplies null, which [the auth constructor](/Users/samhopkinson/webroot/treehouse/app/Services/GitHub/GitHubAuthService.php:34) assigns to a string property. The landing page resolves this service even for local-only use. Missing configuration must be a supported state; retain the public client ID separately from secrets.
2. **P1 — Failed native remote commands can display success.** [The exit handler](/Users/samhopkinson/webroot/treehouse/app/Livewire/RepoView.php:1154) ignores the exit code and clears stderr progress, then emits a success toast unless a pull left conflicts. Preserve output and distinguish success, rejection, authentication failure, timeout and cancellation. [Clone completion](/Users/samhopkinson/webroot/treehouse/app/Livewire/CloneRepo.php:221) also needs alias/exit-code validation; a `.git` directory alone is not proof that checkout completed.
3. **P2 — Errors disappear during refresh.** Merge, stash apply/pop and browser pull set an error before [data reload clears it](/Users/samhopkinson/webroot/treehouse/app/Livewire/RepoView.php:117). Keep operation results separate from repository refresh state.
4. **P2 — Staging and preview have correctness gaps.** [New-file preview](/Users/samhopkinson/webroot/treehouse/app/Services/Git/GitService.php:228) uses ordinary `git diff`, which excludes untracked content. [Status parsing](/Users/samhopkinson/webroot/treehouse/app/Services/Git/Parsers/StatusParser.php:105) keeps Git's quoted filename representation, and [view handlers](/Users/samhopkinson/webroot/treehouse/resources/views/livewire/repo-view.blade.php:993) embed names in single-quoted JavaScript expressions. Use NUL-delimited status, proper pathname decoding and safe JavaScript serialization. [Unstaging a rename](/Users/samhopkinson/webroot/treehouse/app/Livewire/RepoView.php:568) must include both paths so the old-path deletion is not left staged.
5. **P2 — Conflict handling does not track the operation.** [Conflict rows](/Users/samhopkinson/webroot/treehouse/resources/views/livewire/repo-view.blade.php:980) provide no dedicated resolution/stage controls. The generic merge-abort flow is not sufficient for rebase, revert or stash conflicts. Ordinary conflict diffs may also use combined diff syntax that the current parser does not handle.
6. **P2 — Paired branch deletion is inconsistent.** [Remote resolution](/Users/samhopkinson/webroot/treehouse/app/Services/Git/GitService.php:853) can strip part of a slash-containing local name without an upstream. With multiple remotes, the UI and service can choose different matches. Carry the exact confirmed local/remote pair through the operation.
7. **Work authentication needs a complete path.** [OAuth tokens](/Users/samhopkinson/webroot/treehouse/app/Services/GitHub/GitHubAuthService.php:141) serve GitHub API requests, while clone/push/pull invoke Git independently. Verify SSH agent or HTTPS credential-helper behavior when launched from Finder. The [picker's owner-only filter](/Users/samhopkinson/webroot/treehouse/app/Services/GitHub/GitHubRepoService.php:39) omits organization-membership/collaborator repositories. Local repository use must remain available without GitHub login.

The existing suite is valuable but mostly tests parsers, mocked Git arguments and selected Livewire behavior. It does not cover the native failure above. Some real-Git checks [depend on personal checkout paths](/Users/samhopkinson/webroot/treehouse/tests/Unit/Services/Git/GitServiceTest.php:1198) and can skip on another machine. Replace those dependencies with disposable repositories and a local bare remote.

**Recommended implementation sequence**

1. **Make existing operations dependable — approximately 2–3 development days.** Fix the reported startup, command-result, filename, rename and branch-pair issues. Keep errors visible after refresh. Give each process a repository-specific operation ID and prevent conflicting mutations while it runs. Add disposable-repository tests for successful and rejected pushes, missing upstreams, merge failures, new files, quoted/Unicode paths and renames, plus native event tests. Completion means failures are clearly reported and staging leaves exactly the intended index contents.

2. **Complete your merge/rebase workflow — approximately 3–5 days.** Add “Rebase current branch onto…” with explicit target selection, clean-tree checks and operation status. Detect merge/rebase state using Git-resolved metadata paths; provide Continue, Abort and an explicitly confirmed Skip where applicable. Support resolving files in an external editor initially, refreshing and marking resolutions staged. Show the current operation and block completion with unresolved conflicts. Make pull behavior explicit instead of silently inheriting an unknown policy. If publishing rebased commits requires it, provide a separate, confirmed `--force-with-lease` action. Completion means clean rebase, conflict/continue, abort and restart-during-rebase all work against disposable repos. Interactive squash/reorder can follow later.

3. **Prepare the Apple Silicon app — approximately 2–4 days.** Keep Laravel/Livewire/NativePHP. Set consistent Treehouse name, stable bundle ID, release version, author metadata and app icons (`public/icon.png` and `public/icon.icns`). Enable an asset prebuild hook; remove development hot-file/config-cache dependencies. Disable the currently enabled, unconfigured updater for the first release. Verify support/security status of the resolved desktop runtime before shipping, updating compatibly if needed.

   NativePHP supplies PHP and Electron. For a self-contained Git client, package an ARM64 Git distribution with its required libraries and remote/credential helpers, preserve its notices, and centralize executable resolution for both synchronous and background operations. Reuse the user's authorized SSH/HTTPS setup; do not bundle credentials. A smaller initial beta may use installed Git, but it must detect it at first launch and clearly state that dependency. The bundled route is the recommended release target.

   Use a minimal release environment containing only required public configuration. Keep signing secrets and developer data out of the bundle. Retain NativePHP's built-in exclusions for databases, sessions and logs, and inspect the resulting artifact. Verify writable state lives in the user's application-data directory, survives updates, and is not written inside the installed app. Validate token protection and key persistence; use macOS Keychain-backed storage for saved OAuth credentials.

4. **Build, sign and notarize — approximately 1–2 days once credentials are available.** Build on macOS for `arm64`. NativePHP's installed CLI already accepts the required target; its Electron builder produces architecture-labelled disk images in `nativephp/electron/dist`. [NativePHP build documentation](https://nativephp.com/docs/desktop/2/publishing/building/)

   Use a Developer ID Application certificate with its private key available to the build. Supply the NativePHP notarization credentials through the build environment (`NATIVEPHP_APPLE_ID`, `NATIVEPHP_APPLE_ID_PASS`, `NATIVEPHP_APPLE_TEAM_ID`). Check signing of nested executable dependencies and hardened-runtime requirements. Sign and notarize the distribution, and staple/validate the applicable tickets. A successful build command alone is not this gate. [Apple notarization guidance](https://developer.apple.com/documentation/security/notarizing-macos-software-before-distribution)

5. **Prove installation and document handoff — approximately 1–2 days.** Test a transferred/downloaded artifact on another Apple Silicon Mac or a fresh user environment, then on the actual work Mac. Drag Treehouse to Applications, eject the image and launch through Finder. Verify startup without PHP, Composer, Node, Herd or a development server; bundled Git resolution; fresh database creation; authentication; and all the workflows below. Retain the previous installer, publish a checksum and short release notes, and document manual upgrades. Signing does not override any employer device policy. [Apple distribution/testing guidance](https://developer.apple.com/documentation/xcode/packaging-mac-software-for-distribution)

These are planning estimates for focused implementation, approximately **9–16 development days** overall. A basic development `.dmg` should be achievable sooner, but it would retain the current correctness gaps. Certificate setup, work-device requirements and runtime compatibility can add elapsed time.

After the release configuration and fixes, the build entry point is:

```sh
composer install --no-interaction --prefer-dist
npm ci
php artisan test --compact
npm run build
php artisan native:build mac arm64
```

Run this from a controlled release checkout with a sanitized build environment. NativePHP prunes development dependencies in its build copy. Expected artifact naming, once app name/version are configured, is `Treehouse-0.1.0-arm64.dmg`; actual output must be inspected. No publishing command or automatic upload is required to transfer the installer yourself.

Release acceptance checks:

- The app launches from Applications after the DMG is ejected, passes signature/Gatekeeper and ticket validation, and works without development tools.
- A fresh install contains no developer login, recent repositories, logs or private environment values; missing GitHub configuration cannot prevent startup.
- Open a local repository, inspect branch/upstream state, review new/modified/deleted/renamed files, stage selected files, unstage, commit and refresh external changes.
- Authenticate to the actual work host; clone, fetch, pull and push. Rejected pushes and offline/authentication failures produce actionable errors, with no success toast.
- Merge and rebase cleanly; create conflicts; resolve and continue; abort; restart mid-operation and recover correctly. Verify resulting history and index through Git.
- Long branch names and representative work history remain readable; improve the graph's column sizing and add history paging if the 200-commit limit hides needed branches.
- Upgrade to a second test version while preserving settings, credentials and recent repositories. Existing repositories are untouched by installation or upgrade.

Once those gates pass, add hunk staging, better graph navigation and saved tab sessions in that order. Defer interactive rebase, a built-in three-way editor, hosting dashboards, worktree management and AI features until your everyday workflow needs them. This keeps the first release focused on the parts of GitKraken you actually use.
