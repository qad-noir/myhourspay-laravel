<?php

namespace Tests\Feature;

use App\Models\MobilePushDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MobilePushConcurrencyTest extends TestCase
{
    public function test_two_mysql_workers_cannot_claim_the_same_delivery(): void
    {
        if (getenv('MHP_MYSQL_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Opt-in isolated MySQL concurrency evidence.');
        }
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('127.0.0.1', config('database.connections.mysql.host'));
        $this->assertSame('13367', (string) config('database.connections.mysql.port'));
        $this->assertSame('mhp_mobile_test', config('database.connections.mysql.database'));
        $user = User::factory()->create();
        $workspace = $user->ownedWorkspaces()->create(['name' => 'Push concurrency test']);
        $token = $user->createToken('mobile:Concurrent', ['mobile:access'], now()->addHour());
        $device = MobilePushDevice::create(['user_id' => $user->id, 'personal_access_token_id' => $token->accessToken->id,
            'platform' => 'android', 'device_name' => 'Test', 'enabled' => true, 'token' => 'fake-only', 'token_hash' => hash('sha256', Str::random(64))]);
        $id = DB::table('mobile_push_deliveries')->insertGetId(['user_id' => $user->id, 'workspace_id' => $workspace->id,
            'device_id' => $device->id, 'work_date' => now()->toDateString(), 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $barrier = storage_path('framework/push-test-'.Str::uuid());
        $workers = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/mobile-push-claim-worker.php')], base_path(), null,
                    json_encode(['delivery_id' => $id, 'barrier' => $barrier]), 20);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 12;
            while (! str_contains($workers[0]->getOutput(), 'ready') || ! str_contains($workers[1]->getOutput(), 'ready')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers did not reach the barrier.');
                }
                usleep(10000);
            }
            touch($barrier);
            $codes = array_map(fn ($worker) => $worker->wait(), $workers);
            sort($codes);
            $this->assertSame([0, 2], $codes);
            $this->assertSame(1, DB::table('mobile_push_deliveries')->where('id', $id)->value('attempts'));
            $this->assertSame('sending', DB::table('mobile_push_deliveries')->where('id', $id)->value('state'));
        } finally {
            foreach ($workers as $worker) {
                $worker->stop();
            }
            if (is_file($barrier)) {
                unlink($barrier);
            }
            $user->tokens()->delete();
            $workspace->forceDelete();
            $user->forceDelete();
        }
    }
}
