<?php
namespace Tests\Feature;

use App\Models\{Department,User,Role,Plan,PlanItem,PlanItemAssignment,StrategicObjective,PeriodicPerformance,PerformanceRemark,PerformanceAttachment,PeriodClosure};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MonthlyEntrySaveTest extends TestCase
{
    use RefreshDatabase;
    private Department $department;
    private User $user;
    private PlanItem $item;
    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->department = Department::create(['name'=>'Monthly department','code'=>'MON','reporting_type'=>'monthly']);
        $this->user = User::factory()->create(['department_id'=>$this->department->id,'must_change_password'=>false]);
        $this->user->roles()->attach(Role::create(['name'=>'system_admin']));
        $plan = Plan::create(['title'=>'Monthly plan','department_id'=>$this->department->id,'year'=>2026,'status'=>'approved','type'=>'annual','created_by'=>$this->user->id]);
        $objective = StrategicObjective::create(['code'=>'1','description'=>'Monthly KPI','department_id'=>$this->department->id,'status'=>'approved','unit'=>'Tons','weight'=>100]);
        $this->item = PlanItem::create(['plan_id'=>$plan->id,'strategic_objective_id'=>$objective->id,'july_target'=>100]);
        PlanItemAssignment::create(['plan_item_id'=>$this->item->id,'performer_id'=>$this->department->id,'status'=>'approved_for_experts']);
        $this->url = route('monthly.entry.store',[2026,'july']);
        $this->actingAs($this->user);
    }

    private function payload($actual = 25): array
    {
        return ['department_id'=>$this->department->id,'entries'=>[$this->item->id=>['actual_value'=>$actual,'notes'=>'Verified totals']],
            'remarks'=>[['type'=>'achievement','text'=>'Recorded monthly total']]];
    }

    public function test_invalid_values_and_remarks_are_rejected_before_any_write(): void
    {
        $payload = $this->payload('invalid');
        $payload['remarks'][0]['type'] = 'invalid';
        $this->post($this->url,$payload)->assertSessionHasErrors(['entries.'.$this->item->id.'.actual_value','remarks.0.type']);
        $this->assertDatabaseCount('periodic_performances',0);
        $this->assertDatabaseCount('performance_remarks',0);
        $this->post($this->url,$this->payload(-1))->assertSessionHasErrors('entries.'.$this->item->id.'.actual_value');
        $this->post($this->url,$this->payload(10000000000))->assertSessionHasErrors('entries.'.$this->item->id.'.actual_value');
    }

    public function test_blank_actual_does_not_create_zero_and_explicit_zero_is_saved(): void
    {
        $this->post($this->url,$this->payload(''))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('periodic_performances',0);
        $this->post($this->url,$this->payload(0))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('periodic_performances',['plan_item_id'=>$this->item->id,'actual_value'=>0,'workflow_status'=>'draft','status'=>'pending']);
        $this->post($this->url,$this->payload(12))->assertRedirect();
        $this->post($this->url,$this->payload(''))->assertRedirect();
        $this->assertDatabaseHas('periodic_performances',['plan_item_id'=>$this->item->id,'actual_value'=>12]);
    }

    public function test_certified_month_cannot_be_changed(): void
    {
        PeriodClosure::create(['department_id'=>$this->department->id,'year'=>2026,'month'=>'july','certified_by'=>$this->user->id,'certified_at'=>now()]);
        $this->post($this->url,$this->payload())->assertForbidden();
        $this->assertDatabaseCount('periodic_performances',0);
    }

    public function test_foreign_item_rolls_back_earlier_rows(): void
    {
        $payload = $this->payload();
        $payload['entries'][999999] = ['actual_value'=>10];
        $this->post($this->url,$payload)->assertForbidden();
        $this->assertDatabaseCount('periodic_performances',0);
        $this->assertDatabaseCount('performance_remarks',0);
    }

    public function test_successful_save_keeps_review_stage_and_stores_related_data(): void
    {
        PeriodicPerformance::create(['plan_item_id'=>$this->item->id,'period'=>'2026-july','actual_value'=>10,'status'=>'pending','workflow_status'=>'submitted_to_chief']);
        $payload = $this->payload(35);
        $payload['attachments'] = [UploadedFile::fake()->create('proof.pdf',10,'application/pdf')];
        $this->post($this->url,$payload)->assertRedirect(route('monthly.dashboard'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('periodic_performances',['plan_item_id'=>$this->item->id,'actual_value'=>35,'workflow_status'=>'submitted_to_chief']);
        $this->assertDatabaseHas('performance_remarks',['remark_type'=>'achievement','remark_text'=>'Recorded monthly total']);
        $attachment = PerformanceAttachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->file_path);
        $this->assertDatabaseHas('audit_logs',['action'=>'monthly_entry_submitted']);
    }

    public function test_invalid_period_and_nested_input_are_rejected(): void
    {
        $this->post(route('monthly.entry.store',[2026,'invalid']),$this->payload())->assertSessionHasErrors('month');
        $payload = $this->payload();
        $payload['entries'][$this->item->id]['notes'] = ['invalid'];
        $this->post($this->url,$payload)->assertSessionHasErrors('entries.'.$this->item->id.'.notes');
        $this->assertDatabaseCount('periodic_performances',0);
    }

    public function test_attachment_failure_rolls_back_values_remarks_and_files(): void
    {
        PeriodicPerformance::create(['plan_item_id'=>$this->item->id,'period'=>'2026-july','actual_value'=>10,'status'=>'pending','workflow_status'=>'draft']);
        PerformanceRemark::create(['department_id'=>$this->department->id,'year'=>2026,'month'=>'july','remark_type'=>'general','remark_text'=>'Original remark']);
        PerformanceAttachment::creating(function ($attachment) {
            if ($attachment->original_name === 'second.pdf') throw new \RuntimeException('Simulated attachment failure');
        });
        try {
            $payload = $this->payload();
            $payload['attachments'] = [UploadedFile::fake()->create('first.pdf',10,'application/pdf'),UploadedFile::fake()->create('second.pdf',10,'application/pdf')];
            $this->post($this->url,$payload)->assertStatus(500);
            $this->assertDatabaseHas('periodic_performances',['plan_item_id'=>$this->item->id,'actual_value'=>10]);
            $this->assertDatabaseHas('performance_remarks',['remark_text'=>'Original remark']);
            $this->assertDatabaseCount('performance_attachments',0);
            $this->assertDatabaseCount('audit_logs',0);
            $this->assertSame([],Storage::disk('local')->allFiles());
        } finally {
            PerformanceAttachment::flushEventListeners();
        }
    }
}
