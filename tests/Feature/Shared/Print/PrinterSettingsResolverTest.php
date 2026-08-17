<?php

declare(strict_types=1);

namespace Tests\Feature\Shared\Print;

use App\Models\Tenant;
use App\Modules\Shared\Services\Print\PrinterSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrinterSettingsResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_when_no_settings_present(): void
    {
        $tenant = Tenant::factory()->create();

        $resolver = new PrinterSettingsResolver($tenant);

        $this->assertSame('window_print', $resolver->driver());
        $this->assertSame('127.0.0.1', $resolver->host());
        $this->assertSame(9100, $resolver->port());
        $this->assertTrue($resolver->cashDrawerEnabled());
        $this->assertSame(2, $resolver->cashDrawerChannel());
    }

    public function test_reads_settings_from_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'settings' => [
                'pos_hardware' => [
                    'printer_driver' => 'esc_pos',
                    'printer_host' => '192.168.1.50',
                    'printer_port' => 9101,
                    'cash_drawer_after_payment' => false,
                    'cash_drawer_channel' => 4,
                ],
            ],
        ]);

        $resolver = new PrinterSettingsResolver($tenant);

        $this->assertSame('esc_pos', $resolver->driver());
        $this->assertSame('192.168.1.50', $resolver->host());
        $this->assertSame(9101, $resolver->port());
        $this->assertFalse($resolver->cashDrawerEnabled());
        $this->assertSame(4, $resolver->cashDrawerChannel());
    }
}