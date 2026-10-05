<?php

use App\Services\MobilePushDelivery;
use Illuminate\Contracts\Console\Kernel;

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
$claim = app(MobilePushDelivery::class)->claim($input['delivery_id']);
echo $claim ? 'claimed' : 'already_claimed';
exit($claim ? 0 : 2);
