<?php

namespace App\Providers;

use App\Events\CloneRepoRequested;
use App\Events\OpenRepoRequested;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        Menu::create(
            Menu::app(),
            Menu::label('File')->submenu(
                Menu::label('Open Repository...')
                    ->hotkey('CmdOrCtrl+O')
                    ->event(OpenRepoRequested::class),
                Menu::label('Clone Repository...')
                    ->hotkey('CmdOrCtrl+Shift+C')
                    ->event(CloneRepoRequested::class),
            ),
            Menu::edit(),
            Menu::view(),
            Menu::window(),
        );

        Window::open()
            ->width(1200)
            ->height(800)
            ->minWidth(900)
            ->minHeight(600)
            ->titleBarHidden()
            ->rememberState();
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
            // Keep PHP's request body limit above Livewire's application-level
            // guard. Livewire snapshots can be several megabytes for large
            // repositories and are sent as JSON request bodies.
            'post_max_size' => '16M',
            'max_input_time' => '120',
        ];
    }
}
