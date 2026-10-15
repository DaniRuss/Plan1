<?php
namespace Tests\Feature;

use App\Models\{Department, Plan, PlanItem, StrategicObjective, PeriodicPerformance, WeeklyPerformance, User, Role};
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ScorecardLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private function setupUser(string $role = 'system_admin'): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        Gate::define('view_scorecard', fn ($user) => true);
        $user = User::factory()->create(['must_change_password' => false]);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());
        return $user;
    }

    private function item(User $user, string $code, string $frequency = 'monthly'): PlanItem
    {
        $department = Department::create(['name' => $code, 'code' => $code, 'reporting_type' => $frequency, 'is_active' => true]);
        $objective = StrategicObjective::create(['code' => $code, 'department_id' => $department->id, 'description' => 'KPI', 'weight' => 10, 'unit' => 'Tons', 'target_distribution' => 'sum', 'status' => 'approved']);
        $plan = Plan::create(['department_id' => $department->id, 'year' => 2026, 'status' => 'approved', 'type' => 'annual', 'title' => 'Plan', 'created_by' => $user->id]);
        return PlanItem::create(['plan_id' => $plan->id, 'strategic_objective_id' => $objective->id, 'july_target' => 100, 'weight' => 10]);
    }

    public function test_scores_are_normalized_ties_share_rank_and_missing_data_is_unranked(): void
    {
        $user = $this->setupUser();
        foreach (['A' => 70, 'B' => 70, 'C' => 0] as $code => $actual) {
            $item = $this->item($user, $code);
            PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => '2026-july', 'actual_value' => $actual, 'status' => 'approved', 'workflow_status' => 'approved_by_head_office']);
        }
        $this->item($user, 'Missing');
        $rows = $this->actingAs($user)->get(route('scorecard.leaderboard'))->assertOk()->viewData('leaderboard');
        $this->assertSame([70.0,70.0,0.0,null], array_column($rows, 'score'));
        $this->assertSame([1,1,3,null], array_column($rows, 'rank'));
        $this->assertSame('unavailable', $rows[0]['trend']);
        $this->assertSame('Awaiting reviewed results', $rows[3]['state']);
    }

    public function test_reviewed_weekly_records_are_used_and_wrong_year_records_are_ignored(): void
    {
        $user = $this->setupUser();
        $item = $this->item($user, 'Weekly', 'weekly');
        foreach ([2026 => 30, 2025 => 99] as $year => $actual) {
            WeeklyPerformance::create(['plan_item_id' => $item->id, 'year' => $year, 'month' => 'july', 'week_number' => 1, 'planned_value' => 100, 'actual_value' => $actual, 'workflow_status' => 'reviewed_no_correction_required']);
        }
        $rows = $this->actingAs($user)->get(route('scorecard.leaderboard'))->assertOk()->viewData('leaderboard');
        $this->assertSame(30.0, $rows[0]['score']);
    }

    public function test_department_scope_and_invalid_month_are_enforced(): void
    {
        $user = $this->setupUser('department_chief');
        $own = $this->item($user, 'Own');
        $this->item($user, 'Other');
        $user->update(['department_id' => $own->plan->department_id]);
        $this->actingAs($user)->get(route('scorecard.leaderboard'))->assertOk()->assertSee('Own')->assertDontSee('Other');
        $this->get(route('scorecard.leaderboard', ['month' => 'invalid']))->assertSessionHasErrors('month');
    }

    public function test_incomplete_reviewed_results_are_not_ranked(): void
    {
        $user = $this->setupUser();
        $item = $this->item($user, 'Partial');
        $objective = StrategicObjective::create(['code' => 'Extra', 'department_id' => $item->plan->department_id, 'description' => 'Extra KPI', 'weight' => 10, 'unit' => 'Tons', 'status' => 'approved']);
        PlanItem::create(['plan_id' => $item->plan_id, 'strategic_objective_id' => $objective->id, 'july_target' => 100, 'weight' => 10]);
        PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => '2026-july', 'actual_value' => 100, 'status' => 'approved', 'workflow_status' => null]);
        $rows = $this->actingAs($user)->get(route('scorecard.leaderboard'))->assertOk()->viewData('leaderboard');
        $this->assertNull($rows[0]['score']);
        $this->assertNull($rows[0]['rank']);
        $this->assertSame(50.0, $rows[0]['coverage']);
        $this->assertSame('Incomplete reviewed results', $rows[0]['state']);
    }
}
