<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingMigrationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_migration_can_resume_without_losing_reviews(): void
    {
        Schema::table('billing_payment_reviews', function (Blueprint $table) {
            $table->dropUnique(['stripe_object_id']);
            $table->dropIndex(['stripe_customer_id']);
        });
        DB::table('billing_payment_reviews')->insert([
            'stripe_object_id' => 're_retained', 'kind' => 'refund', 'amount' => 100,
            'currency' => 'gbp', 'status' => 'succeeded',
        ]);
        Schema::drop('billing_checkout_confirmations');
        $migration = require database_path('migrations/2026_09_09_180000_harden_billing_event_processing.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseHas('billing_payment_reviews', ['stripe_object_id' => 're_retained']);
        $this->assertTrue(Schema::hasIndex('billing_payment_reviews', ['stripe_object_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('billing_payment_reviews', ['stripe_customer_id']));
        $this->assertTrue(Schema::hasTable('billing_checkout_confirmations'));
    }

    public function test_missing_review_table_is_logged_without_exposing_sql_even_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        Schema::drop('billing_payment_reviews');
        $response = $this->actingAs($admin)->getJson(route('admin.billing.payment-reviews.data').'?draw=1&start=0&length=10');
        $response->assertStatus(500)->assertJsonStructure(['message', 'reference']);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertDatabaseHas('operational_incidents', ['reference' => $response->json('reference')]);
    }
}
