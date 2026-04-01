<p align="center">
<strong>Treehouse</strong><br>
A Mac-first desktop Git client
</p>

<p align="center">
<img src="https://img.shields.io/badge/build-passing-brightgreen" alt="Build Status">
<img src="https://img.shields.io/badge/tests-211%20passed-brightgreen" alt="Tests">
<img src="https://img.shields.io/badge/version-0.1.0--dev-blue" alt="Latest Stable Version">
<img src="https://img.shields.io/badge/license-MIT-green" alt="License">
</p>

## About

Treehouse is a native macOS desktop Git client built with Laravel 12, NativePHP Desktop v2, Livewire 3, Alpine.js, and Tailwind CSS. It uses the system `git` CLI under the hood and connects to GitHub for authentication and repository management.

## Features

- **Visual commit graph** rendered on HTML Canvas with bezier curves and lane-based layout
- **Full staging workflow** -- stage, unstage, discard, and commit with inline diffs
- **Branch operations** -- create, checkout, delete, merge with conflict detection
- **Tag management** -- first-class UI for lightweight and annotated tags (create, delete, push)
- **Stash operations** -- stash, apply, pop, drop with named stash support
- **Remote sync** -- fetch, pull, push with async progress via NativePHP ChildProcess
- **GitHub device flow auth** -- OAuth login without needing a server callback
- **Repo picker** -- clone any of your GitHub repos from a filterable dropdown
- **Diff viewer** -- unified diff view for staged, unstaged, and commit diffs
- **Commit detail panel** -- view full commit metadata, parent hashes, and diffs
- **Keyboard shortcuts** -- Cmd+R (refresh), Cmd+Enter (commit), Cmd+Shift+F/L/P (focus panels)
- **Auto-refresh** -- repo data refreshes on window focus
- **Copy actions** -- click-to-copy on commit hashes, branch names, and tag names
- **Toast notifications** -- success/error feedback for all operations
- **Resizable sidebar** -- draggable panel divider with min/max constraints

## Tech Stack

| Layer | Technology |
|-------|------------|
| Framework | Laravel 12 |
| Desktop Runtime | NativePHP Desktop v2 |
| Frontend | Livewire 3 + Alpine.js |
| Styling | Tailwind CSS 4 |
| Graph Renderer | HTML Canvas |
| Git | System `git` CLI via Symfony Process |
| Auth | GitHub Device Flow OAuth |
| Database | SQLite (NativePHP managed) |

## Requirements

- macOS
- PHP 8.2+
- Node.js 18+
- Git
- Composer

## Setup

```bash
# Install dependencies
composer install
npm install

# Copy environment file
cp .env.example .env
php artisan key:generate

# Add your GitHub OAuth app client ID to .env
# GITHUB_CLIENT_ID=your_client_id

# Run database migrations
php artisan migrate
php artisan native:migrate

# Build frontend assets
npm run build

# Run the native app
php artisan native:run
```

## Development

```bash
# Run with Vite dev server (hot reload for CSS/JS)
npm run dev

# Run tests (can add --parallel)
php artisan test

# Run the app in browser (limited -- no NativePHP features)
php artisan serve
```

## Architecture

```
app/
  DTOs/              # Value objects for Git data (Commit, Branch, Tag, FileStatus, etc.)
  Services/
    Git/             # Git CLI wrapper, parsers, error translation
    GitHub/          # Device flow auth, repo listing
  Livewire/          # UI components (RepoView, Landing, CloneRepo, GitHubLogin)
  Events/            # NativePHP menu events
  Providers/         # NativeAppServiceProvider (menu, window config)
resources/
  js/commit-graph.js # Canvas-based commit graph renderer
  views/livewire/    # Blade templates for each component
```

The Git layer never uses libgit2 or any Git reimplementation. Every operation runs through `GitCommandRunner`, which executes `git` commands via Symfony Process and pipes output through dedicated parsers that return typed DTOs.

```bash
php artisan test
```

## License

MIT
