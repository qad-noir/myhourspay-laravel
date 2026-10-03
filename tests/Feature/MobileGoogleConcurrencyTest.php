<?php

namespace Tests\Feature;

use App\Services\MobileGoogleChallenge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MobileGoogleConcurrencyTest extends TestCase
{
    public function test_only_one_of_two_mysql_connections_can_consume_the_challenge(): void
    {
        if (getenv('MHP_MYSQL_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Opt-in isolated MySQL concurrency test.');
        }
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('127.0.0.1', config('database.connections.mysql.host'));
        $this->assertSame('13367', (string) config('database.connections.mysql.port'));
        $this->assertSame('mhp_mobile_test', config('database.connections.mysql.database'));
        $challenge = app(MobileGoogleChallenge::class)->issue();
        $hash = hash('sha256', Str::random(64));
        $barrier = storage_path('framework/google-test-'.Str::uuid());
        $workers = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/google-challenge-worker.php')], base_path(), null,
                    json_encode([...$challenge, 'barrier' => $barrier, 'token_hash' => $hash]), 20);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 12;
            while (! str_contains($workers[0]->getOutput(), 'ready') || ! str_contains($workers[1]->getOutput(), 'ready')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers did not reach the synchronization barrier.');
                }
                usleep(10000);
            }
            touch($barrier);
            $codes = array_map(fn ($worker) => $worker->wait(), $workers);
            sort($codes);
            $this->assertSame([0, 2], $codes);
            $outputs = implode('|', array_map(fn ($worker) => $worker->getOutput(), $workers));
            $this->assertStringContainsString('google_challenge_used', $outputs);
            $this->assertSame(1, DB::table('mobile_social_credentials')->where('token_hash', $hash)->count());
            $this->assertNotNull(DB::table('mobile_google_challenges')->where('id', $challenge['challenge_id'])->value('consumed_at'));
        } finally {
            foreach ($workers as $worker) {
                $worker->stop();
            }
            if (is_file($barrier)) {
                unlink($barrier);
            }
            DB::table('mobile_google_challenges')->where('id', $challenge['challenge_id'])->delete();
            DB::table('mobile_social_credentials')->where('token_hash', $hash)->delete();
        }
    }
}
