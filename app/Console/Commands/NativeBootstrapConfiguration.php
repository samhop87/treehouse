<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'treehouse:native-bootstrap',
    description: 'Load NativePHP startup configuration and PHP directives in one Laravel boot',
)]
class NativeBootstrapConfiguration extends Command
{
    protected $signature = 'treehouse:native-bootstrap';

    public function handle(): int
    {
        /** @var ProvidesPhpIni $provider */
        $provider = app(config('nativephp.provider'));
        $phpIni = method_exists($provider, 'phpIni') ? $provider->phpIni() : [];

        $this->output->write(json_encode([
            'config' => config('nativephp'),
            'phpIni' => $phpIni,
        ], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
