<?php

use App\Services\MobileGoogleChallenge;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

// Separate connections exercise the production transaction boundary on MySQL.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.host') !== '127.0.0.1'
    || (string) config('database.connections.mysql.port') !== '13367' || config('database.connections.mysql.database') !== 'mhp_mobile_test') {
    exit(10);
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
echo "ready\n";
flush();
$deadline = microtime(true) + 10;
while (! is_file($input['barrier'])) {
    if (microtime(true) > $deadline) {
        exit(11);
    }
    usleep(10000);
    clearstatcache();
}
try {
    DB::transaction(function () use ($input) {
        app(MobileGoogleChallenge::class)->consume($input['challenge_id'], ['nonce' => $input['nonce']]);
        usleep(250000);
        DB::table('mobile_social_credentials')->insert(['token_hash' => $input['token_hash'], 'expires_at' => now()->addMinutes(5)]);
    });
    echo 'consumed';
} catch (HttpResponseException $exception) {
    echo $exception->getResponse()->getData(true)['code'];
    exit(2);
}
