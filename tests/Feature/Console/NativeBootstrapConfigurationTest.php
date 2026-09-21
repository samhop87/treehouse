<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NativeBootstrapConfigurationTest extends TestCase
{
    public function test_it_returns_native_config_and_php_ini_settings_in_one_response(): void
    {
        $this->assertSame(0, Artisan::call('treehouse:native-bootstrap'));

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(config('nativephp.app_id'), $payload['config']['app_id']);
        $this->assertSame([
            'post_max_size' => '16M',
            'max_input_time' => '120',
        ], $payload['phpIni']);
    }
}
