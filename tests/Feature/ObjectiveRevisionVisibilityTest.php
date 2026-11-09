<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Department, Role, StrategicObjective, User};
use App\Services\ObjectivePublisher;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ObjectiveRevisionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;
    private Department $department;
    private StrategicObjective $main;
    private StrategicObjective $activity;
    private StrategicObjective $detail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->department = Department::create(['name'=>'Operation','code'=>'OP','is_active'=>true]);
        $this->editor = User::factory()->create(['name'=>'Revision Editor','department_id'=>$this->department->id,'must_change_password'=>false]);
        $this->editor->roles()->attach(Role::where('name',User::ROLE_PLAN_REPORT_DIRECTORATE)->firstOrFail());
        $base = ['description'=>'Approved plan','weight'=>20,'unit'=>'Count','department_id'=>$this->department->id,
            'lead_department_id'=>$this->department->id,'performers'=>[$this->department->id],'target_year'=>2026,'annual_target'=>100,
            'july_target'=>100,'q1_target'=>100,'target_distribution'=>'sum','goal_direction'=>'increase','status'=>'approved','is_active'=>true];
        $this->main = StrategicObjective::create($base+['code'=>'1']);
        $this->activity = StrategicObjective::create(array_replace($base,['code'=>'1.1','parent_id'=>$this->main->id,'weight'=>5]));
        $this->detail = StrategicObjective::create(array_replace($base,['code'=>'1.1.1','parent_id'=>$this->activity->id,'weight'=>2]));
        app(ObjectivePublisher::class)->publishTree($this->main,$this->editor);
        $this->travelTo(Carbon::parse('2026-11-03 17:15:00',config('app.timezone')));
    }

    private function edit(StrategicObjective $objective, array $changes = []): void
    {
        $payload = $objective->only(['code','description','weight','unit','annual_target','july_target','target_distribution','goal_direction','target_year','parent_id','lead_department_id','performers','is_active','include_in_parent_rollup']);
        $payload['review_change_reason'] = 'Source verified; details-page-only reason.';
        $this->actingAs($this->editor)->put(route('objectives.update',$objective),array_replace($payload,$changes))
            ->assertRedirect(route('objectives.index'))->assertSessionHasNoErrors();
    }

    private function metadata(string $html, int $id): array
    {
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        $nodes = (new DOMXPath($doc))->query('//*[@data-objective-edit-meta="'.$id.'"]');
        return ['count'=>$nodes->length,'text'=>$nodes->length ? $nodes->item(0)->textContent : ''];
    }

    public function test_each_edited_hierarchy_level_shows_only_editor_role_and_time_on_the_list(): void
    {
        foreach ([$this->main,$this->activity,$this->detail] as $objective) $this->edit($objective,['description'=>'Updated '.$objective->code]);
        $page = $this->get(route('objectives.index'))->assertOk();
        foreach ([$this->main,$this->activity,$this->detail] as $objective) {
            $meta = $this->metadata($page->getContent(),$objective->id);
            $this->assertSame(1,$meta['count']);
            $this->assertStringContainsString('Revision Editor',$meta['text']);
            $this->assertStringContainsString('Plan and Report Directorate',$meta['text']);
            $this->assertStringContainsString('03 Nov 2026',$meta['text']);
            $this->assertStringContainsString('05:15 PM',$meta['text']);
            $this->assertStringNotContainsString('Source verified',$meta['text']);
            $this->assertStringNotContainsString('Previous value',$meta['text']);
            $this->assertSame('approved',$objective->fresh()->status);
        }
        $page->assertDontSee('details-page-only reason');
    }

    public function test_details_keep_old_new_values_and_reason_with_the_editors_saved_role(): void
    {
        $this->edit($this->activity,['annual_target'=>120]);
        $this->get(route('objectives.show',$this->activity))->assertOk()
            ->assertSee('Source verified; details-page-only reason.')->assertSee('Previous value')->assertSee('New value')
            ->assertSee('Annual target')->assertSee('Plan and Report Directorate')->assertSee('After approval');
    }

    public function test_role_snapshot_and_editor_name_survive_later_account_changes(): void
    {
        $this->edit($this->activity,['description'=>'Updated activity']);
        $log = AuditLog::where('model_id',$this->activity->id)->where('action','strategic_objective_updated')->latest('id')->firstOrFail();
        $details = json_decode($log->details,true);
        $this->assertArrayHasKey('roles',$details['editor']);
        $this->assertSame(User::ROLE_PLAN_REPORT_DIRECTORATE,$details['editor']['roles'][0]['name']);
        $this->editor->roles()->sync([Role::where('name',User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT)->value('id')]);
        $this->editor->update(['name'=>'Renamed Editor']);
        $this->editor->refresh();
        $page = $this->get(route('objectives.index'))->assertOk();
        $meta = $this->metadata($page->getContent(),$this->activity->id);
        $this->assertStringContainsString('Revision Editor',$meta['text']);
        $this->assertStringContainsString('Plan and Report Directorate',$meta['text']);
        $this->assertStringNotContainsString('Plan and Report Expert',$meta['text']);
        $detailsPage = $this->get(route('objectives.show',$this->activity))->assertOk();
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">'.$detailsPage->getContent());
        $historyText = (new DOMXPath($doc))->query('//*[@id="revision-history"]')->item(0)->textContent;
        $this->assertStringNotContainsString('Renamed Editor',$historyText);
    }

    public function test_untouched_and_preapproval_only_revisions_are_not_marked_as_postapproval_edits(): void
    {
        $this->editor->roles()->sync([Role::where('name',User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT)->value('id')]);
        $this->editor = $this->editor->fresh();
        $this->activity->update(['status'=>'draft']);
        $this->edit($this->activity,['description'=>'Draft change']);
        $this->activity->update(['status'=>'approved']);
        $page = $this->get(route('objectives.index'))->assertOk();
        $this->assertSame(0,$this->metadata($page->getContent(),$this->main->id)['count']);
        $this->assertSame(0,$this->metadata($page->getContent(),$this->activity->id)['count']);
        $this->assertSame(0,$this->metadata($page->getContent(),$this->detail->id)['count']);
    }

    public function test_noop_save_keeps_the_last_real_edit_attribution(): void
    {
        $this->edit($this->activity,['description'=>'Updated activity']);
        $this->travelTo(Carbon::parse('2026-11-03 18:30:00',config('app.timezone')));
        $this->edit($this->activity->fresh());
        $page = $this->get(route('objectives.index'))->assertOk();
        $text = $this->metadata($page->getContent(),$this->activity->id)['text'];
        $this->assertStringContainsString('05:15 PM',$text);
        $this->assertStringNotContainsString('06:30 PM',$text);
    }

    public function test_later_draft_revision_does_not_hide_the_previous_approved_edit(): void
    {
        $this->edit($this->activity,['description'=>'Postapproval change']);
        $this->editor->roles()->sync([Role::where('name',User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT)->value('id')]);
        $this->editor = $this->editor->fresh();
        $this->activity->refresh()->update(['status'=>'draft']);
        $this->travelTo(Carbon::parse('2026-11-03 18:30:00',config('app.timezone')));
        $this->edit($this->activity->fresh(),['description'=>'Later draft change']);
        $this->activity->update(['status'=>'approved']);
        $page = $this->get(route('objectives.index'))->assertOk();
        $text = $this->metadata($page->getContent(),$this->activity->id)['text'];
        $this->assertStringContainsString('05:15 PM',$text);
        $this->assertStringNotContainsString('06:30 PM',$text);
    }

    public function test_changed_calculated_ancestor_weight_gets_a_dependency_revision_but_unchanged_ancestor_does_not(): void
    {
        $this->main->update(['is_calculated_weight'=>true,'weight'=>5]);
        $this->edit($this->activity,['weight'=>7]);
        $logs = AuditLog::where('model_type',StrategicObjective::class)->where('model_id',$this->main->id)->where('action','strategic_objective_updated')->get();
        $this->assertCount(1,$logs);
        $details = json_decode($logs->first()->details,true);
        $this->assertEquals(5,$details['changes']['weight']['before']);
        $this->assertEquals(7,$details['changes']['weight']['after']);
        $this->assertSame($this->activity->id,$details['source_objective']['id']);
        $this->edit($this->activity->fresh(),['description'=>'Description-only update']);
        $this->assertSame(1,AuditLog::where('model_type',StrategicObjective::class)->where('model_id',$this->main->id)->where('action','strategic_objective_updated')->count());
    }

    public function test_saved_attribution_remains_when_the_editor_reference_is_missing(): void
    {
        $this->edit($this->activity,['description'=>'Updated activity']);
        $reader = User::factory()->create(['department_id'=>$this->department->id,'must_change_password'=>false]);
        $reader->roles()->attach(Role::where('name',User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT)->firstOrFail());
        AuditLog::where('action','strategic_objective_updated')->update(['user_id'=>null]);
        $this->activity->update(['last_edited_by'=>null]);
        $page = $this->actingAs($reader)->get(route('objectives.index'))->assertOk();
        $meta = $this->metadata($page->getContent(),$this->activity->id);
        $this->assertStringContainsString('Revision Editor',$meta['text']);
        $this->assertStringContainsString('Plan and Report Directorate',$meta['text']);
    }

    public function test_legacy_role_fallback_is_identified_as_current_not_a_fabricated_historical_role(): void
    {
        AuditLog::create(['user_id'=>$this->editor->id,'action'=>'strategic_objective_updated','model_type'=>StrategicObjective::class,'model_id'=>$this->activity->id,
            'details'=>json_encode(['stage'=>'approved','editor'=>['name'=>'Legacy Editor'],'changes'=>['description'=>['before'=>'Old','after'=>'New']]])]);
        $page = $this->actingAs($this->editor)->get(route('objectives.index'))->assertOk();
        $meta = $this->metadata($page->getContent(),$this->activity->id);
        $this->assertStringContainsString('Legacy Editor',$meta['text']);
        $this->assertStringContainsString('Current role: Plan and Report Directorate',$meta['text']);
    }

    public function test_list_metadata_uses_bounded_queries_not_one_query_per_activity(): void
    {
        $this->edit($this->activity,['description'=>'Updated activity']);
        DB::enableQueryLog();
        $page = $this->get(route('objectives.index'))->assertOk();
        $before = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i=2;$i<=14;$i++) {
            $extra = $this->detail->replicate();
            $extra->code = '1.1.'.$i;
            $extra->save();
            AuditLog::create(['user_id'=>$this->editor->id,'action'=>'strategic_objective_updated','model_type'=>StrategicObjective::class,'model_id'=>$extra->id,'details'=>json_encode(['stage'=>'approved','editor'=>['name'=>'Legacy Editor'],'changes'=>['description'=>['before'=>'Old','after'=>'New']]])]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $larger = $this->get(route('objectives.index'))->assertOk();
        $after = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(1,$this->metadata($larger->getContent(),$extra->id)['count']);
        $this->assertLessThanOrEqual($before+3,$after);
    }

    public function test_list_uses_the_most_recent_edit_timestamp_even_when_older_history_was_imported_later(): void
    {
        $this->edit($this->activity,['description'=>'Current correction']);
        AuditLog::forceCreate(['user_id'=>$this->editor->id,'action'=>'strategic_objective_updated','model_type'=>StrategicObjective::class,'model_id'=>$this->activity->id,
            'created_at'=>now()->subDay(),'details'=>json_encode(['stage'=>'approved','editor'=>['name'=>'Older Imported Editor'],'changes'=>['description'=>['before'=>'Old','after'=>'Older correction']]])]);
        $page = $this->get(route('objectives.index'))->assertOk();
        $text = $this->metadata($page->getContent(),$this->activity->id)['text'];
        $this->assertStringContainsString('Revision Editor',$text);
        $this->assertStringNotContainsString('Older Imported Editor',$text);
    }

    public function test_approved_change_notification_links_to_details_without_exposing_the_reason_on_the_list(): void
    {
        $owner = User::factory()->create(['department_id'=>$this->department->id,'must_change_password'=>false]);
        $owner->roles()->attach(Role::where('name',User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT)->firstOrFail());
        $this->activity->update(['owner_id'=>$owner->id]);
        $this->edit($this->activity,['description'=>'Updated activity']);
        $this->actingAs($owner)->get(route('objectives.index'))->assertOk()->assertDontSee('details-page-only reason');
        $this->get(route('objectives.show',$this->activity))->assertOk()->assertSee('details-page-only reason');
    }

    public function test_frequency_request_and_rejection_do_not_claim_the_approved_objective_was_edited(): void
    {
        $this->actingAs($this->editor)->post(route('objectives.frequency.request',$this->activity),['reporting_frequency'=>'monthly','change_reason'=>'Report this indicator monthly.'])->assertRedirect();
        $page = $this->get(route('objectives.index'))->assertOk();
        $this->assertSame(0,$this->metadata($page->getContent(),$this->activity->id)['count']);
        $head = User::factory()->create(['department_id'=>$this->department->id,'must_change_password'=>false]);
        $head->roles()->attach(Role::where('name',User::ROLE_PLAN_REPORT_HEAD_OFFICE)->firstOrFail());
        $this->actingAs($head)->post(route('objectives.frequency.decide',$this->activity),['decision'=>'reject'])->assertRedirect();
        $page = $this->get(route('objectives.index'))->assertOk();
        $this->assertSame(0,$this->metadata($page->getContent(),$this->activity->id)['count']);
    }

    public function test_approved_frequency_change_saves_the_approvers_role_and_displays_real_attribution(): void
    {
        $this->actingAs($this->editor)->post(route('objectives.frequency.request',$this->activity),['reporting_frequency'=>'monthly','change_reason'=>'Report this indicator monthly.'])->assertRedirect();
        $head = User::factory()->create(['name'=>'Frequency Approver','department_id'=>$this->department->id,'must_change_password'=>false]);
        $head->roles()->attach(Role::where('name',User::ROLE_PLAN_REPORT_HEAD_OFFICE)->firstOrFail());
        $this->actingAs($head)->post(route('objectives.frequency.decide',$this->activity),['decision'=>'approve'])->assertRedirect();
        $log = AuditLog::where('action','strategic_objective_updated')->where('model_id',$this->activity->id)->latest('id')->firstOrFail();
        $details = json_decode($log->details,true);
        $this->assertArrayHasKey('roles',$details['editor']);
        $this->assertSame(User::ROLE_PLAN_REPORT_HEAD_OFFICE,$details['editor']['roles'][0]['name']);
        $page = $this->get(route('objectives.index'))->assertOk();
        $text = $this->metadata($page->getContent(),$this->activity->id)['text'];
        $this->assertStringContainsString('Frequency Approver',$text);
        $this->assertStringContainsString('Plan and Report Chief (Head)',$text);
    }
}
