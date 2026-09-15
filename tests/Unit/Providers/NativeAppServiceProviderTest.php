<?php

namespace Tests\Unit\Providers;

use App\Providers\NativeAppServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NativeAppServiceProviderTest extends TestCase
{
    #[Test]
    public function native_php_allows_livewire_request_bodies(): void
    {
        $settings = (new NativeAppServiceProvider)->phpIni();

        $this->assertSame('16M', $settings['post_max_size']);
        $this->assertSame('120', $settings['max_input_time']);
    }
}
