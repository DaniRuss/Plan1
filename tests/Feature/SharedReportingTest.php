<?php
namespace Tests\Feature;

use App\Models\{Department, User, Role, Plan, PlanItem, StrategicObjective, PlanItemAssignment, ReportingTeam, ReportingSubmission, ReportingEntry, WeeklyPerformance, DailyPerformance, PeriodicPerformance};
use App\Services\SharedReporting;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SharedReportingTest extends TestCase
{
    use RefreshDatabase;
    private Department $department;
    private User $chief;
    private User $coordinator;
    private User $backup;
    private array $reviewers;
    private SharedReporting $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        $this->department = Department::create(['name'=>'Cargo','code'=>'CG','reporting_type'=>'weekly']);
        $this->chief = $this->user('department_chief');
        $this->coordinator = $this->user('department_expert');
        $this->backup = $this->user('department_expert');
        $this->reviewers = [$this->user('department_directorate'),$this->user('department_directorate')];
        $this->service = app(SharedReporting::class);
        $this->actingAs($this->chief);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['department_id'=>$this->department->id,'must_change_password'=>false,'is_active'=>true]);
        $user->roles()->attach(Role::where('name',$role)->firstOrFail());
        return $user;
    }

    private function report(string $frequency = 'weekly'): ReportingSubmission
    {
        $this->department->update(['reporting_type'=>$frequency]);
        $this->service->team($this->department,$this->chief,['coordinator_id'=>$this->coordinator->id,'backup_id'=>$this->backup->id,'version'=>0,'reason'=>'Initial reporting team']);
        $plan = Plan::create(['department_id'=>$this->department->id,'year'=>2026,'status'=>'approved','type'=>'annual','title'=>'Cargo plan','created_by'=>$this->chief->id]);
        foreach ([0,1] as $index) {
            $objective = StrategicObjective::create(['code'=>'1.'.($index+1),'description'=>'KPI '.$index,'department_id'=>$this->department->id,'weight'=>50,'unit'=>'Tons','status'=>'approved','target_distribution'=>'sum']);
            $item = PlanItem::create(['plan_id'=>$plan->id,'strategic_objective_id'=>$objective->id,'weight'=>50,'july_target'=>100]);
            PlanItemAssignment::create(['plan_item_id'=>$item->id,'performer_id'=>$this->department->id,'status'=>'approved_for_experts','assigned_directorate_user_id'=>$this->reviewers[$index]->id]);
        }
        return $this->service->start($this->coordinator,['department_id'=>$this->department->id,'year'=>2026,'month'=>'july','frequency'=>$frequency,'week'=>$frequency==='monthly'?0:1]);
    }

    private function saveReady(ReportingEntry $entry, User $user, float $actual = 0): void
    {
        $this->actingAs($user)->put(route('shared-reporting.save',$entry),['version'=>$entry->version,'state'=>'ready','actual'=>$actual,'planned'=>25])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function action(ReportingSubmission $s, User $user, string $action, array $extra = []): void
    {
        $this->actingAs($user)->post(route('shared-reporting.transition',$s),$extra+['version'=>$s->fresh()->version,'action'=>$action])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_workflow_notifications_target_next_reviewers_and_include_return_comments(): void
    {
        $s = $this->report();
        foreach ($s->entries as $entry) $this->saveReady($entry,$this->coordinator,10);
        $expert = $this->user('department_expert');
        $planExpert = $this->user('plan_report_department_expert');
        $planDirectorate = $this->user('plan_report_directorate');
        $head = $this->user('plan_report_head_office');
        \Illuminate\Support\Facades\Notification::fake();
        $this->action($s,$this->coordinator,'submit');
        foreach ($this->reviewers as $reviewer) {
            \Illuminate\Support\Facades\Notification::assertSentTo($reviewer,\App\Notifications\WorkflowNotification::class,
                fn ($n) => $n->toArray($reviewer)['title'] === 'Shared report submitted' && $n->toArray($reviewer)['url'] === route('shared-reporting.show',$s));
        }
        \Illuminate\Support\Facades\Notification::assertNotSentTo($this->coordinator,\App\Notifications\WorkflowNotification::class);
        $sections = $s->sections()->orderBy('id')->get();
        $this->action($s,$sections[0]->reviewer,'return_section',['section_id'=>$sections[0]->id,'version'=>$sections[0]->version,'note'=>'Please reconcile station totals']);
        foreach ([$expert,$this->coordinator,$this->backup] as $recipient) {
            \Illuminate\Support\Facades\Notification::assertSentTo($recipient,\App\Notifications\WorkflowNotification::class,
                fn ($n) => $n->toArray($recipient)['type'] === 'warning' && str_contains($n->toArray($recipient)['message'],'Please reconcile station totals'));
        }
        $this->saveReady($sections[0]->entries()->first(),$this->coordinator,12);
        $this->action($s,$this->coordinator,'submit');
        foreach ($s->sections()->get() as $section) $this->action($s,$section->reviewer,'review_section',['section_id'=>$section->id,'version'=>$section->version]);
        \Illuminate\Support\Facades\Notification::assertSentTo($this->chief,\App\Notifications\WorkflowNotification::class);
        foreach ([[$this->chief,'approve_chief',$planExpert],[$planExpert,'forward_plan_report',$planDirectorate],[$planDirectorate,'forward_head',$head],[$head,'approve_final',$this->coordinator]] as [$actor,$action,$recipient]) {
            $this->action($s,$actor,$action);
            \Illuminate\Support\Facades\Notification::assertSentTo($recipient,\App\Notifications\WorkflowNotification::class);
        }
        \Illuminate\Support\Facades\Notification::assertSentTo($planExpert,\App\Notifications\WorkflowNotification::class,
            fn ($n) => $n->toArray($planExpert)['title'] === 'Shared report finally approved');
    }

    public function test_reviewer_assignment_notifies_the_assigned_reviewer(): void
    {
        $s = $this->report();
        $entry = $s->entries()->first();
        $replacement = $this->user('department_directorate');
        \Illuminate\Support\Facades\Notification::fake();
        $this->service->assign($entry,$this->chief,['version'=>$entry->version,'reviewer_id'=>$replacement->id,'reason'=>'Reviewer reassigned']);
        \Illuminate\Support\Facades\Notification::assertSentTo($replacement,\App\Notifications\WorkflowNotification::class,
            fn ($n) => $n->toArray($replacement)['title'] === 'Shared reporting reviewer assigned');
        \Illuminate\Support\Facades\Notification::assertNotSentTo($this->chief,\App\Notifications\WorkflowNotification::class);
    }

    public function test_page_saves_all_states_and_values_in_one_request(): void
    {
        $s = $this->report(); $rows = $s->entries()->get();
        $this->actingAs($this->coordinator)->get(route('shared-reporting.show',$s))
            ->assertOk()->assertSee('Save all activities')->assertDontSee('Save this activity');
        $this->put(route('shared-reporting.save-all',$s),['entries'=>[
            $rows[0]->id=>['version'=>1,'state'=>'ready','actual'=>0,'planned'=>25],
            $rows[1]->id=>['version'=>1,'state'=>'not_applicable','reason'=>'No scheduled work this week'],
        ]])->assertRedirect(route('shared-reporting.show',$s))->assertSessionHasNoErrors();
        $this->assertSame('ready',$rows[0]->fresh()->state);
        $this->assertSame('not_applicable',$rows[1]->fresh()->state);
        $this->assertDatabaseHas('weekly_performances',['plan_item_id'=>$rows[0]->plan_item_id,'actual_value'=>0]);
        $this->assertDatabaseMissing('weekly_performances',['plan_item_id'=>$rows[1]->plan_item_id]);
    }

    public function test_batch_validation_and_stale_rows_roll_back_all_changes_and_files(): void
    {
        $s = $this->report(); $rows = $s->entries()->get();
        $payload = ['entries'=>[
            $rows[0]->id=>['version'=>1,'state'=>'ready','actual'=>10,'files'=>[UploadedFile::fake()->create('proof.pdf',10,'application/pdf')]],
            $rows[1]->id=>['version'=>1,'state'=>'ready','actual'=>null],
        ]];
        $this->actingAs($this->coordinator)->put(route('shared-reporting.save-all',$s),$payload)->assertSessionHasErrors('report');
        $this->assertSame(1,$rows[0]->fresh()->version);
        $this->assertDatabaseMissing('weekly_performances',['plan_item_id'=>$rows[0]->plan_item_id]);
        $this->assertCount(0,Storage::disk('local')->allFiles('shared_reporting'));
        $payload['entries'][$rows[1]->id] = ['version'=>2,'state'=>'ready','actual'=>20];
        $this->put(route('shared-reporting.save-all',$s),$payload)->assertStatus(409);
        $this->assertSame(1,$rows[0]->fresh()->version);
        $this->assertCount(0,Storage::disk('local')->allFiles('shared_reporting'));
    }

    public function test_batch_allows_unfinished_blank_drafts_without_creating_zero(): void
    {
        $s = $this->report(); $rows = $s->entries()->get();
        $this->actingAs($this->coordinator)->put(route('shared-reporting.save-all',$s),['entries'=>[
            $rows[0]->id=>['version'=>1,'state'=>'ready','actual'=>12],
            $rows[1]->id=>['version'=>1,'state'=>'draft','actual'=>null],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('draft',$rows[1]->fresh()->state);
        $this->assertDatabaseMissing('weekly_performances',['plan_item_id'=>$rows[1]->plan_item_id]);
    }

    public function test_chief_publication_preserves_values_until_corrected_report_is_resubmitted(): void
    {
        $s=$this->report();
        foreach($s->entries as $entry) $this->saveReady($entry,$this->coordinator,10);
        $this->action($s,$this->coordinator,'submit');
        $entry=$s->entries()->first();
        $this->actingAs($this->reviewers[1])->put(route('shared-reporting.save',$entry),['version'=>$entry->version,'state'=>'ready','actual'=>99,'reason'=>'Wrong reviewer must not edit'])->assertForbidden();
        $this->actingAs($this->reviewers[0])->put(route('shared-reporting.save',$entry),['version'=>$entry->version,'state'=>'ready','actual'=>15,'planned'=>25,'reason'=>'Verified station source documents'])->assertRedirect()->assertSessionHasNoErrors();
        foreach($s->sections()->get() as $section) $this->action($s,$section->reviewer,'review_section',['section_id'=>$section->id,'version'=>$section->version]);
        $entry->refresh();
        $this->actingAs($this->chief)->put(route('shared-reporting.save',$entry),['version'=>$entry->version,'state'=>'ready','actual'=>20,'planned'=>25,'reason'=>'Reconciled department total'])->assertRedirect()->assertSessionHasNoErrors();
        $url=route('weekly.entry',[2026,'july',1,'department_id'=>$this->department->id]);
        $this->get($url)->assertOk()->assertViewHas('entries',fn($rows)=>$rows->isEmpty());
        WeeklyPerformance::where('plan_item_id',$entry->plan_item_id)->update(['metadata'=>json_encode(['verified'=>1])]);
        $this->action($s,$this->chief,'approve_chief');
        $this->assertSame(['verified'=>1],$s->fresh()->department_snapshot[0]['metadata']);
        $this->get($url)->assertOk()->assertViewHas('entries',fn($rows)=>$rows->get($entry->plan_item_id)->metadata===['verified'=>1])->assertSee('Department approved')->assertViewHas('entries',fn($rows)=>(float)$rows->get($entry->plan_item_id)->actual_value===20.0);
        $this->get(route('weekly.report',[2026,'july',1,'department_id'=>$this->department->id]))->assertOk();
        $this->action($s,$this->user('plan_report_department_expert'),'return_department',['note'=>'Please reconcile the revised totals']);
        $entry->refresh();
        $this->actingAs($this->chief)->put(route('shared-reporting.save',$entry),['version'=>$entry->version,'state'=>'ready','actual'=>30,'planned'=>25,'reason'=>'Corrected the returned total'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get($url)->assertOk()->assertSee('Returned for correction')->assertViewHas('entries',fn($rows)=>(float)$rows->get($entry->plan_item_id)->actual_value===20.0);
        $this->action($s,$this->chief,'approve_chief');
        $this->get($url)->assertOk()->assertViewHas('entries',fn($rows)=>(float)$rows->get($entry->plan_item_id)->actual_value===30.0);
    }

    public function test_missing_manual_weights_do_not_block_performance_submission(): void
    {
        $s=$this->report();
        $plan=$s->plan;
        $objective=StrategicObjective::create(['code'=>'1.3','description'=>'Unplanned sibling','department_id'=>$this->department->id,'weight'=>20,'unit'=>'Tons','status'=>'approved']);
        PlanItem::create(['plan_id'=>$plan->id,'strategic_objective_id'=>$objective->id,'weight'=>20,'july_target'=>null]);
        $weights=app(\App\Services\PeriodWeightNormalizer::class)->forPeriod($plan->items()->with('strategicObjective')->get(),'july');
        $this->assertNull($weights[$s->entries()->first()->plan_item_id]);
        foreach($s->entries as $entry) $this->saveReady($entry,$this->coordinator,10);
        $this->action($s,$this->coordinator,'submit');
        $this->assertSame('submitted_to_directorate',$s->fresh()->status);
    }

    public function test_shared_numeric_fields_display_at_most_three_decimals(): void
    {
        $s=$this->report(); $entry=$s->entries()->first();
        $this->saveReady($entry,$this->coordinator,55.5678);
        WeeklyPerformance::where('plan_item_id',$entry->plan_item_id)->update(['actual_value'=>55.5678,'planned_value'=>25]);
        $this->get(route('shared-reporting.show',$s))->assertOk()
            ->assertSee('value="55.568"',false)->assertSee('value="25"',false)
            ->assertDontSee('value="55.567800000000"',false)->assertDontSee('value="25.000000000000"',false)
            ->assertSee('data-original-number="55.567800000000"',false);
        $this->assertDatabaseHas('weekly_performances',['plan_item_id'=>$entry->plan_item_id,'actual_value'=>55.5678]);
    }

    public function test_chief_batch_requires_reason_only_for_changed_row_and_preserves_failed_input(): void
    {
        $s=$this->report();
        foreach($s->entries as $entry) $this->saveReady($entry,$this->coordinator,20);
        $this->action($s,$this->coordinator,'submit');
        foreach($s->sections()->get() as $section) $this->action($s,$section->reviewer,'review_section',['section_id'=>$section->id,'version'=>$section->version]);
        $rows=$s->entries()->get();
        WeeklyPerformance::where('plan_item_id',$rows[0]->plan_item_id)->update(['metadata'=>json_encode(['internal_marker'=>1])]);
        $payload=['entries'=>[
            $rows[0]->id=>['version'=>$rows[0]->version,'state'=>'ready','actual'=>'20.000000000000','planned'=>25],
            $rows[1]->id=>['version'=>$rows[1]->version,'state'=>'ready','actual'=>190,'planned'=>25,'notes'=>'Updated source totals','reason'=>'test'],
        ]];
        $this->actingAs($this->chief)->from(route('shared-reporting.show',$s))->put(route('shared-reporting.save-all',$s),$payload)
            ->assertSessionHasErrors('entries.'.$rows[1]->id.'.reason')->assertSessionDoesntHaveErrors('entries.'.$rows[0]->id.'.reason');
        $this->get(route('shared-reporting.show',$s))->assertOk()->assertSee('Updated source totals')->assertSee('at least 5 characters');
        $this->assertDatabaseHas('weekly_performances',['plan_item_id'=>$rows[1]->plan_item_id,'actual_value'=>20]);
        $payload['entries'][$rows[1]->id]['reason']='Corrected against source totals';
        $this->put(route('shared-reporting.save-all',$s),$payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('weekly_performances',['plan_item_id'=>$rows[1]->plan_item_id,'actual_value'=>190]);
        $this->assertSame($rows[0]->version,$rows[0]->fresh()->version);
        $this->assertSame('Corrected against source totals',$rows[1]->fresh()->reason);
    }

    public function test_chief_assigns_distinct_team_and_experts_cannot_change_it(): void
    {
        $this->actingAs($this->coordinator)->put(route('reporting-teams.update',$this->department),['coordinator_id'=>$this->coordinator->id,'backup_id'=>$this->backup->id,'version'=>0,'reason'=>'Initial team'])->assertForbidden();
        $this->actingAs($this->chief)->put(route('reporting-teams.update',$this->department),['coordinator_id'=>$this->coordinator->id,'backup_id'=>$this->backup->id,'version'=>0,'reason'=>'Initial team'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reporting_teams',['department_id'=>$this->department->id,'coordinator_id'=>$this->coordinator->id]);
        $this->get(route('reporting-teams.edit',$this->department))->assertOk();
    }

    public function test_shared_rows_save_independently_and_reject_stale_overwrites(): void
    {
        $s = $this->report(); $entries = $s->entries()->get();
        $this->saveReady($entries[0],$this->coordinator,0);
        $this->saveReady($entries[1],$this->backup,20);
        $this->actingAs($this->backup)->put(route('shared-reporting.save',$entries[0]),['version'=>1,'state'=>'ready','actual'=>99,'planned'=>25])->assertStatus(409);
        $this->assertDatabaseHas('weekly_performances',['plan_item_id'=>$entries[0]->plan_item_id,'actual_value'=>0]);
        $this->assertDatabaseHas('weekly_performances',['plan_item_id'=>$entries[1]->plan_item_id,'actual_value'=>20]);
        $this->get(route('shared-reporting.show',$s))->assertOk()->assertSee('Ready')->assertSee('Last editor');
        $this->get(route('weekly.entry',[2026,'july',1,'department_id'=>$this->department->id]))->assertOk()->assertSee('Awaiting department chief approval');
        $this->post(route('weekly.store'),['department_id'=>$this->department->id,'year'=>2026,'month'=>'july','week'=>1])->assertStatus(409);
    }

    public function test_only_coordinator_submits_and_partial_return_preserves_other_approval(): void
    {
        $s = $this->report();
        foreach ($s->entries as $entry) $this->saveReady($entry,$this->coordinator,10);
        $ordinary = $this->user('department_expert');
        $this->actingAs($ordinary)->post(route('shared-reporting.transition',$s),['version'=>$s->fresh()->version,'action'=>'submit'])->assertForbidden();
        $this->action($s,$this->backup,'submit');
        $sections = $s->sections()->orderBy('id')->get();
        $this->actingAs($this->reviewers[1])->post(route('shared-reporting.transition',$s),['action'=>'review_section','version'=>$sections[0]->version,'section_id'=>$sections[0]->id])->assertForbidden();
        $this->action($s,$this->reviewers[0],'review_section',['section_id'=>$sections[0]->id,'version'=>$sections[0]->version]);
        $this->assertSame('submitted_to_directorate',$s->fresh()->status);
        $this->action($s,$this->reviewers[1],'return_section',['section_id'=>$sections[1]->id,'version'=>$sections[1]->version,'note'=>'Please attach supporting totals']);
        $this->assertSame('reviewed',$sections[0]->fresh()->status);
        $this->assertTrue($this->service->editable($this->coordinator,$sections[1]->entries()->first()));
        $this->assertFalse($this->service->editable($this->coordinator,$sections[0]->entries()->first()));
        $this->actingAs($this->chief)->post(route('shared-reporting.transition',$s),['version'=>$s->fresh()->version,'action'=>'approve_chief'])->assertSessionHasErrors('report');
        $this->saveReady($sections[1]->entries()->first(),$this->backup,12);
        $this->action($s,$this->coordinator,'submit');
        $this->action($s,$this->reviewers[1],'review_section',['section_id'=>$sections[1]->id,'version'=>$sections[1]->fresh()->version]);
        $this->assertSame('submitted_to_chief',$s->fresh()->status);
        foreach ([[$this->chief,'approve_chief'],[$this->user('plan_report_department_expert'),'forward_plan_report'],[$this->user('plan_report_directorate'),'forward_head'],[$this->user('plan_report_head_office'),'approve_final']] as [$user,$action]) $this->action($s,$user,$action);
        $this->assertSame('approved_by_head_office',$s->fresh()->status);
        $this->assertSame(2,WeeklyPerformance::where('workflow_status','approved_by_head_office')->count());
        $this->assertFalse($this->service->editable($this->coordinator,$s->entries()->first()));
    }

    public function test_incomplete_items_and_missing_evidence_block_submission(): void
    {
        $s = $this->report();
        $this->actingAs($this->coordinator)->post(route('shared-reporting.transition',$s),['version'=>$s->version,'action'=>'submit'])->assertSessionHasErrors('report');
        $entry = $s->entries()->first();
        $this->actingAs($this->chief)->put(route('shared-reporting.assign',$entry),['version'=>$entry->version,'reviewer_id'=>$this->reviewers[0]->id,'evidence_required'=>1,'reason'=>'Evidence required for verification'])->assertRedirect()->assertSessionHasNoErrors();
        foreach ($s->entries()->get() as $row) $this->saveReady($row,$this->coordinator,0);
        $this->actingAs($this->coordinator)->post(route('shared-reporting.transition',$s),['version'=>$s->fresh()->version,'action'=>'submit'])->assertSessionHasErrors('report');
        $entry->refresh();
        $this->put(route('shared-reporting.save',$entry),['version'=>$entry->version,'state'=>'ready','actual'=>0,'planned'=>25,'files'=>[UploadedFile::fake()->create('evidence.pdf',10,'application/pdf')]])->assertRedirect()->assertSessionHasNoErrors();
        $this->action($s,$this->coordinator,'submit');
        $this->get(route('shared-reporting.evidence',[$entry,0]))->assertOk()->assertDownload('evidence.pdf');
    }

    public function test_not_applicable_requires_reason_and_never_creates_a_zero_actual(): void
    {
        $s=$this->report('monthly'); $entry=$s->entries()->first();
        $this->actingAs($this->coordinator)->put(route('shared-reporting.save',$entry),['version'=>1,'state'=>'not_applicable'])->assertSessionHasErrors('report');
        $this->put(route('shared-reporting.save',$entry),['version'=>1,'state'=>'not_applicable','reason'=>'No scheduled operations this month'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('periodic_performances',0);
        $this->get(route('shared-reporting.show',$s))->assertOk()->assertSee('Not applicable');
    }

    public function test_daily_entries_generate_one_weekly_summary_and_blank_days_are_not_zero(): void
    {
        $s=$this->report('daily'); $entry=$s->entries()->first();
        $days=array_fill_keys($this->service->days($s),0); $days[1]=12;
        $this->actingAs($this->coordinator)->put(route('shared-reporting.save',$entry),['version'=>1,'state'=>'ready','planned'=>25,'days'=>[1=>12]])->assertSessionHasErrors('report');
        $this->put(route('shared-reporting.save',$entry),['version'=>1,'state'=>'ready','planned'=>25,'days'=>$days])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('daily_performances',count($days));
        $this->assertDatabaseHas('weekly_performances',['plan_item_id'=>$entry->plan_item_id,'actual_value'=>12]);
        $this->assertDatabaseCount('weekly_performances',1);
        $this->get(route('shared-reporting.show',$s))->assertOk();
    }

    public function test_monthly_batch_save_and_review_use_monthly_status_values(): void
    {
        $s = $this->report('monthly');
        $payload = [];
        foreach ($s->entries as $entry) {
            $payload[$entry->id] = ['version'=>$entry->version,'state'=>'ready','actual'=>350];
        }
        $this->actingAs($this->coordinator)->put(route('shared-reporting.save-all',$s),['entries'=>$payload])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('periodic_performances',2);
        $this->assertSame(['pending'],PeriodicPerformance::distinct()->pluck('status')->all());
        $this->action($s,$this->coordinator,'submit');
        $this->assertSame(['pending'],PeriodicPerformance::distinct()->pluck('status')->all());
        $section = $s->sections()->first();
        $this->action($s,$section->reviewer,'return_section',['section_id'=>$section->id,'version'=>$section->version,'note'=>'Please verify the source totals']);
        $this->assertDatabaseHas('periodic_performances',['plan_item_id'=>$section->entries()->first()->plan_item_id,'status'=>'rejected','workflow_status'=>'returned_to_expert']);
    }

    public function test_monthly_submission_uses_existing_metrics_and_is_idempotent(): void
    {
        $s=$this->report('monthly');
        foreach ($s->entries as $entry) $this->saveReady($entry,$this->coordinator,30);
        $this->saveReady($s->entries()->first(),$this->backup,40);
        $this->assertDatabaseCount('periodic_performances',2);
        $this->assertDatabaseCount('weekly_performances',0);
        $this->action($s,$this->backup,'submit');
        $this->actingAs($this->coordinator)->put(route('shared-reporting.save',$s->entries()->first()),['version'=>2,'state'=>'ready','actual'=>90])->assertForbidden();
        $this->put(route('performance.update',[$s->plan_id,'july']),['entries'=>[]])->assertStatus(409);
        $this->get(route('shared-reporting.index',['department_id'=>$this->department->id]))->assertOk()->assertSee('Coordinator:');
    }

    public function test_existing_in_review_period_is_not_rewritten_or_migrated(): void
    {
        $s=$this->report(); $item=$s->entries()->first()->plan_item_id;
        $s->entries()->delete(); $s->sections()->delete(); $s->delete();
        $original=WeeklyPerformance::create(['plan_item_id'=>$item,'year'=>2026,'month'=>'july','week_number'=>1,'planned_value'=>25,'actual_value'=>23,'percentage'=>92,'workflow_status'=>'approved_by_head_office']);
        $this->actingAs($this->coordinator)->post(route('shared-reporting.start'),['department_id'=>$this->department->id,'year'=>2026,'month'=>'july','week'=>1,'frequency'=>'weekly'])->assertSessionHasErrors('report');
        $this->assertDatabaseCount('reporting_submissions',0);
        $this->assertSame('approved_by_head_office',$original->fresh()->workflow_status);
        $this->assertEquals(23,$original->fresh()->actual_value);
        $this->get(route('weekly.entry',[2026,'july',1,'department_id'=>$this->department->id]))->assertOk();
    }

    public function test_foreign_department_cannot_access_entries_and_stale_review_is_rejected(): void
    {
        $s=$this->report(); $entry=$s->entries()->first();
        $foreign=$this->user('department_expert');
        $other=Department::create(['name'=>'Other','code'=>'OTH']);
        $foreign->update(['department_id'=>$other->id]);
        $this->actingAs($foreign)->get(route('shared-reporting.show',$s))->assertForbidden();
        $this->put(route('shared-reporting.save',$entry),['version'=>1,'state'=>'ready','actual'=>1])->assertForbidden();
        foreach ($s->entries as $row) $this->saveReady($row,$this->coordinator,1);
        $this->action($s,$this->coordinator,'submit');
        $section=$s->sections()->first();
        $this->action($s,$this->reviewers[0],'review_section',['section_id'=>$section->id,'version'=>$section->version]);
        $this->actingAs($this->reviewers[0])->post(route('shared-reporting.transition',$s),['action'=>'review_section','section_id'=>$section->id,'version'=>$section->version])->assertStatus(409);
        $this->get(route('shared-reporting.show',$s))->assertOk()->assertSee('Review sections');
    }

    public function test_final_monthly_not_applicable_is_excluded_from_scorecard_without_zero_actual(): void
    {
        $s=$this->report('monthly'); $entries=$s->entries()->get();
        $this->actingAs($this->coordinator)->put(route('shared-reporting.save',$entries[0]),['version'=>1,'state'=>'not_applicable','reason'=>'No planned operations occurred'])->assertRedirect()->assertSessionHasNoErrors();
        $this->saveReady($entries[1],$this->coordinator,60);
        $this->action($s,$this->coordinator,'submit');
        foreach ($s->sections()->orderBy('id')->get() as $index=>$section) $this->action($s,$this->reviewers[$index],'review_section',['section_id'=>$section->id,'version'=>$section->version]);
        foreach ([[$this->chief,'approve_chief'],[$this->user('plan_report_department_expert'),'forward_plan_report'],[$this->user('plan_report_directorate'),'forward_head'],[$this->user('plan_report_head_office'),'approve_final']] as [$user,$action]) $this->action($s,$user,$action);
        $this->assertSame([$entries[0]->plan_item_id],$this->service->notApplicableForMonth($this->department->id,2026,'july'));
        $this->assertDatabaseMissing('periodic_performances',['plan_item_id'=>$entries[0]->plan_item_id]);
        $rows=$this->actingAs($this->user('system_admin'))->get(route('scorecard.leaderboard',['year'=>2026,'month'=>'july']))->assertOk()->viewData('leaderboard');
        $this->assertSame(60.0,$rows[0]['score']);
    }

    public function test_daily_reporting_only_accepts_dates_within_the_reporting_week(): void
    {
        $this->report('daily');
        $weeks=\App\Services\CalendarHelper::getMonthWeeks('july',2026);
        $week=array_key_last($weeks);
        $s=$this->service->start($this->coordinator,['department_id'=>$this->department->id,'year'=>2026,'month'=>'july','frequency'=>'daily','week'=>$week]);
        $expected=(int)\Carbon\Carbon::parse($weeks[$week]['start_full'])->diffInDays(\Carbon\Carbon::parse($weeks[$week]['end_full']))+1;
        $this->assertCount($expected,$this->service->days($s));
        $this->actingAs($this->coordinator)->put(route('shared-reporting.save',$s->entries()->first()),['version'=>1,'state'=>'ready','days'=>[99=>1]])->assertSessionHasErrors('report');
        $this->assertDatabaseCount('daily_performances',0);
    }

    public function test_legacy_monthly_period_aliases_do_not_create_duplicate_actuals(): void
    {
        $s=$this->report('monthly'); $entry=$s->entries()->first();
        PeriodicPerformance::create(['plan_item_id'=>$entry->plan_item_id,'period'=>'july','actual_value'=>12,'status'=>'draft','workflow_status'=>'draft']);
        $this->saveReady($entry,$this->coordinator,40);
        $this->assertDatabaseCount('periodic_performances',1);
        $this->assertDatabaseHas('periodic_performances',['plan_item_id'=>$entry->plan_item_id,'period'=>'2026-july','actual_value'=>40]);
        $this->assertEquals(40,$this->service->values($entry->fresh())['actual']);
    }
}
