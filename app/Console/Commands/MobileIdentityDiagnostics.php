<?php

namespace App\Console\Commands;

use Firebase\JWT\JWT;
use Illuminate\Console\Command;

class MobileIdentityDiagnostics extends Command
{
    protected $signature = 'mobile:identity-diagnostics';

    protected $description = 'Check effective Google audience and UTC time without printing credentials';

    public function handle(): int
    {
        $expected = '69237986520-s7mltqvpk5aqt3l2pljgemcmr8vtnvb1.apps.googleusercontent.com';
        $matches = config('mobile.google_audiences') === [$expected];
        $this->line('google_audience_exact_match='.($matches ? 'true' : 'false'));
        $this->line('server_utc='.gmdate('Y-m-d\TH:i:s\Z'));
        $this->line('jwt_dependency_available='.(class_exists(JWT::class) ? 'true' : 'false'));
        $this->line('UTC output alone does not verify NTP synchronization. Check the host time service.');

        return $matches ? self::SUCCESS : self::FAILURE;
    }
}
