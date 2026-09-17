<?php
namespace Tests\Feature;

use App\Models\HoursEntry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoursUndoTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_is_soft_and_signed_undo_restores_the_same_entry(): void
    {
        [$user, $entry] = $this->entry();
        $url = $this->actingAs($user)->deleteJson(route('hours.entries.destroy', $entry))->assertOk()->json('undo_url');
        $this->assertSoftDeleted($entry);
        $this->postJson($url)->assertOk()->assertJson(['saved' => true]);
        $this->assertFalse($entry->refresh()->trashed());
        $this->postJson($url)->assertOk();
        $this->assertDatabaseCount('hours_entries', 1);
    }

    public function test_other_users_and_expired_links_cannot_restore(): void
    {
        [$user, $entry] = $this->entry();
        $url = $this->actingAs($user)->deleteJson(route('hours.entries.destroy', $entry))->json('undo_url');
        [$other] = $this->entry();
        $this->actingAs($other)->postJson($url)->assertForbidden();
        $this->actingAs($user);
        $this->travel(11)->minutes();
        $this->postJson($url)->assertForbidden();
        $this->assertSoftDeleted($entry);
    }

    private function entry(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::forceCreate(['owner_id'=>$user->id,'name'=>'Undo QA','default_break_type'=>'unpaid','default_break_minutes'=>0,'weekly_target_minutes'=>2400]);
        $workspace->users()->attach($user->id,['role'=>'owner','position'=>'Owner']);
        $user->forceFill(['current_workspace_id'=>$workspace->id])->save();
        $entry = HoursEntry::create(['user_id'=>$user->id,'workspace_id'=>$workspace->id,'work_date'=>'2026-09-17','start_time'=>'09:00','end_time'=>'17:00','break_minutes'=>0,'break_type'=>'paid']);
        return [$user,$entry];
    }
}
