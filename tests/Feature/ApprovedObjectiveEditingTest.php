<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\PeriodicPerformance;
use App\Models\PlanItem;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use App\Services\ObjectivePublisher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApprovedObjectiveEditingTest extends TestCase
{
    use RefreshDatabase;

    private Department $performer;
    private Department $planning;
    private User $owner;
    private User $approver;
    private StrategicObjective $main;
    private StrategicObjective $activity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->performer = Department::create(['name' => 'Operations', 'code' => 'OPS', 'is_active' => true]);
        $this->planning = Department::create(['name' => 'Plan and Report', 'code' => 'PR', 'is_active' => true]);
        $this->owner = $this->user(User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT);
        $this->approver = $this->user(User::ROLE_PLAN_REPORT_HEAD_OFFICE);
        $attributes = [
            'description' => 'Approved inspection plan', 'weight' => 5, 'unit' => 'Count',
            'department_id' => $this->performer->id, 'owner_id' => $this->owner->id,
            'lead_department_id' => $this->performer->id, 'performers' => [$this->performer->id],
            'goal_direction' => 'increase', 'target_year' => 2019, 'annual_target' => 40,
            'july_target' => 10, 'target_distribution' => 'sum', 'reporting_frequency' => 'weekly',
            'status' => 'approved', 'is_active' => true,
        ];
        $this->main = StrategicObjective::create($attributes + ['code' => '1', 'is_calculated_weight' => true]);
        $this->activity = StrategicObjective::create($attributes + ['code' => '1.1', 'parent_id' => $this->main->id]);
        app(ObjectivePublisher::class)->publishTree($this->main, $this->approver);
    }

    public static function planningRoles(): array
    {
        return [
            'expert' => [User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT],
            'directorate' => [User::ROLE_PLAN_REPORT_DIRECTORATE],
            'chief' => [User::ROLE_PLAN_REPORT_HEAD_OFFICE],
        ];
    }

    public static function departmentalRoles(): array
    {
        return [
            'department expert' => [User::ROLE_DEPARTMENT_EXPERT],
            'department directorate' => [User::ROLE_DEPARTMENT_DIRECTORATE],
            'department chief' => [User::ROLE_DEPARTMENT_CHIEF],
        ];
    }

    #[DataProvider('planningRoles')]
    public function test_planning_roles_can_open_approved_main_and_sub_objective_edit_forms(string $role): void
    {
        $editor = $this->user($role);
        foreach ([$this->main, $this->activity] as $objective) {
            $this->actingAs($editor)->get(route('objectives.edit', $objective))
                ->assertOk()->assertSee('name="review_change_reason"', false);
            $this->get(route('objectives.show', $objective))->assertOk()
                ->assertSee(route('objectives.edit', $objective), false);
        }
        $this->get(route('objectives.index'))->assertOk()
            ->assertSee(route('objectives.edit', $this->main), false)
            ->assertSee(route('objectives.edit', $this->activity), false);
    }

    #[DataProvider('planningRoles')]
    public function test_approved_edits_keep_status_and_sync_published_values_without_resetting_reviews_or_actuals(string $role): void
    {
        $editor = $this->user($role);
        $item = PlanItem::where('strategic_objective_id', $this->activity->id)->firstOrFail();
        $assignment = $item->assignments()->firstOrFail();
        $assignment->update(['status' => 'approved_for_experts']);
        $actual = PeriodicPerformance::create([
            'plan_item_id' => $item->id, 'period' => '2019-july', 'actual_value' => 8,
            'status' => 'approved', 'workflow_status' => 'approved',
        ]);

        $this->actingAs($editor)->put(route('objectives.update', $this->activity), $this->payload())
            ->assertRedirect(route('objectives.index'))->assertSessionHasNoErrors();

        $objective = $this->activity->fresh();
        $this->assertSame('Corrected approved inspection details', $objective->description);
        $this->assertSame('approved', $objective->status);
        $this->assertSame($this->performer->id, $objective->department_id);
        $this->assertSame($this->owner->id, $objective->owner_id);
        $this->assertSame(1, $objective->revision_number);
        $this->assertSame($editor->id, $objective->last_edited_by);
        $this->assertSame('approved', $objective->last_edited_stage);
        $this->assertSame('Corrected the approved target using the source plan.', $objective->last_change_reason);
        $this->assertEquals(80, $item->fresh()->annual_target);
        $this->assertEquals(20, $item->fresh()->july_target);
        $this->assertEquals(7, $item->fresh()->weight);
        $this->assertSame('tons', $item->fresh()->unit);
        $this->assertEquals(7, $this->main->fresh()->weight);
        $this->assertEquals(7, PlanItem::where('strategic_objective_id', $this->main->id)->firstOrFail()->weight);
        $this->assertSame('approved_for_experts', $assignment->fresh()->status);
        $this->assertSame(2, PlanItem::count());
        $this->assertEquals(8, $actual->fresh()->actual_value);
        $this->assertSame('approved', $actual->fresh()->workflow_status);
        $log = AuditLog::where('action', 'strategic_objective_updated')->where('model_id', $objective->id)->firstOrFail();
        $this->assertSame($editor->id, $log->user_id);
        $details = json_decode($log->details, true);
        $this->assertSame('Corrected the approved target using the source plan.', $details['reason']);
        $this->assertEquals(40, $details['changes']['annual_target']['before']);
        $this->assertEquals(80, $details['changes']['annual_target']['after']);
    }

    #[DataProvider('planningRoles')]
    public function test_approved_edit_requires_a_change_reason(string $role): void
    {
        $payload = $this->payload();
        unset($payload['review_change_reason']);
        $this->actingAs($this->user($role))->put(route('objectives.update', $this->activity), $payload)
            ->assertSessionHasErrors('review_change_reason');
        $this->assertSame('Approved inspection plan', $this->activity->fresh()->description);
    }

    #[DataProvider('departmentalRoles')]
    public function test_departmental_roles_cannot_edit_the_approved_organization_objective(string $role): void
    {
        $editor = $this->user($role, $this->performer);
        $this->actingAs($editor)->get(route('objectives.edit', $this->activity))->assertForbidden();
        $this->put(route('objectives.update', $this->activity), $this->payload())->assertForbidden();
        $this->get(route('objectives.index'))->assertOk()
            ->assertDontSee(route('objectives.edit', $this->main), false)
            ->assertDontSee(route('objectives.edit', $this->activity), false);
        $this->assertSame('Approved inspection plan', $this->activity->fresh()->description);
    }

    public function test_approved_details_edit_does_not_bypass_reporting_frequency_approval(): void
    {
        $payload = $this->payload();
        $payload['reporting_frequency'] = 'monthly';
        $this->actingAs($this->owner)->put(route('objectives.update', $this->activity), $payload)
            ->assertSessionHasErrors('reporting_frequency');
        $this->assertSame('weekly', $this->activity->fresh()->reporting_frequency);
        $this->assertSame('Approved inspection plan', $this->activity->fresh()->description);
    }

    public function test_publishing_failure_rolls_back_the_approved_edit_and_revision(): void
    {
        $this->main->update(['target_year' => null]);
        $this->actingAs($this->owner)->put(route('objectives.update', $this->activity), $this->payload())
            ->assertSessionHasErrors('target_year');
        $this->assertSame('Approved inspection plan', $this->activity->fresh()->description);
        $this->assertSame(0, $this->activity->fresh()->revision_number);
        $this->assertEquals(10, PlanItem::where('strategic_objective_id', $this->activity->id)->firstOrFail()->july_target);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'strategic_objective_updated', 'model_id' => $this->activity->id]);
    }

    public function test_editing_approved_details_does_not_approve_an_existing_draft_department_plan(): void
    {
        $plan = PlanItem::where('strategic_objective_id', $this->activity->id)->firstOrFail()->plan;
        $plan->update(['status' => 'draft', 'approved_by' => null, 'approved_at' => null]);

        $this->actingAs($this->owner)->put(route('objectives.update', $this->activity), $this->payload())
            ->assertRedirect(route('objectives.index'))->assertSessionHasNoErrors();

        $this->assertSame('draft', $plan->fresh()->status);
        $this->assertNull($plan->fresh()->approved_by);
        $this->assertNull($plan->fresh()->approved_at);
        $this->assertSame('approved', $this->activity->fresh()->status);
    }

    public function test_published_objective_year_cannot_be_changed_by_a_details_edit(): void
    {
        $payload = $this->payload();
        $payload['target_year'] = 2020;
        $this->actingAs($this->owner)->put(route('objectives.update', $this->activity), $payload)
            ->assertSessionHasErrors('target_year');
        $this->assertEquals(2019, $this->activity->fresh()->target_year);
        $this->assertSame(2, PlanItem::count());
    }

    public function test_published_performer_cannot_be_removed_by_a_details_edit(): void
    {
        $other = Department::create(['name' => 'Engineering', 'code' => 'ENG', 'is_active' => true]);
        $payload = $this->payload();
        $payload['lead_department_id'] = $other->id;
        $payload['performers'] = [$other->id];
        $this->actingAs($this->owner)->put(route('objectives.update', $this->activity), $payload)
            ->assertSessionHasErrors('performers');
        $this->assertSame([$this->performer->id], $this->activity->fresh()->performers);
    }

    public function test_additional_performer_gets_an_assignment_without_resetting_the_existing_review(): void
    {
        $item = PlanItem::where('strategic_objective_id', $this->activity->id)->firstOrFail();
        $assignment = $item->assignments()->firstOrFail();
        $assignment->update(['status' => 'approved_for_experts']);
        $other = Department::create(['name' => 'Engineering', 'code' => 'ENG', 'is_active' => true]);
        $payload = $this->payload();
        $payload['performers'][] = $other->id;
        $this->actingAs($this->owner)->put(route('objectives.update', $this->activity), $payload)
            ->assertRedirect(route('objectives.index'))->assertSessionHasNoErrors();

        $this->assertSame('approved_for_experts', $assignment->fresh()->status);
        $newItem = PlanItem::where('strategic_objective_id', $this->activity->id)->whereHas('plan', fn ($q) => $q->where('department_id', $other->id))->firstOrFail();
        $this->assertSame('pending_chief_approval', $newItem->assignments()->firstOrFail()->status);
        $this->assertEquals(20, $newItem->july_target);
        $this->assertSame('approved', $this->activity->fresh()->status);
    }

    public function test_an_approved_activity_cannot_move_under_an_unapproved_ancestor(): void
    {
        $draft = StrategicObjective::create([
            'code' => '2', 'description' => 'Unapproved main objective', 'weight' => 20, 'unit' => 'Count',
            'department_id' => $this->performer->id, 'target_year' => 2019, 'status' => 'draft',
        ]);
        $payload = $this->payload();
        $payload['parent_id'] = $draft->id;
        $this->actingAs($this->owner)->put(route('objectives.update', $this->activity), $payload)
            ->assertSessionHasErrors('parent_id');
        $this->assertSame($this->main->id, $this->activity->fresh()->parent_id);
    }

    private function user(string $role, ?Department $department = null): User
    {
        $user = User::factory()->create(['department_id' => ($department ?? $this->planning)->id, 'is_active' => true, 'must_change_password' => false]);
        $user->roles()->sync([Role::where('name', $role)->value('id')]);
        return $user;
    }

    private function payload(): array
    {
        return [
            'code' => $this->activity->code, 'description' => 'Corrected approved inspection details',
            'weight' => 7, 'unit' => 'Tons', 'annual_target' => 80, 'july_target' => 20,
            'parent_id' => $this->main->id, 'include_in_parent_rollup' => true,
            'goal_direction' => 'increase', 'target_distribution' => 'sum', 'target_year' => 2019,
            'lead_department_id' => $this->performer->id, 'performers' => [$this->performer->id],
            'review_change_reason' => 'Corrected the approved target using the source plan.',
        ];
    }
}
