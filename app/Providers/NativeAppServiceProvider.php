<?php

namespace App\Providers;

use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\Window;
use Native\Desktop\Contracts\ProvidesPhpIni;

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
                    ->event(\App\Events\OpenRepoRequested::class),
                Menu::label('Clone Repository...')
                    ->hotkey('CmdOrCtrl+Shift+C')
                    ->event(\App\Events\CloneRepoRequested::class),
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
        ];
    }
}
