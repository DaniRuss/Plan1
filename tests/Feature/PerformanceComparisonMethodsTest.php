<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PeriodicPerformance;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceComparisonMethodsTest extends TestCase
{
    use RefreshDatabase;

    private function user(Department $department, string $role = 'plan_report_department_expert'): User
    {
        $user = User::factory()->create(['department_id' => $department->id, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());

        return $user;
    }

    private function createPerformance(Department $department, User $user, int $year, string $month, float $actual, string $state = 'approved_by_head_office'): void
    {
        $objective = StrategicObjective::create(['department_id' => $department->id, 'code' => $department->id.'.'.$year.'.'.$month, 'description' => 'Cargo volume', 'unit' => 'Tons', 'weight' => 10, 'status' => 'approved']);
        $plan = Plan::create(['department_id' => $department->id, 'year' => $year, 'title' => 'Annual plan', 'type' => 'annual', 'status' => 'approved', 'created_by' => $user->id]);
        $item = PlanItem::create(['plan_id' => $plan->id, 'strategic_objective_id' => $objective->id, $month.'_target' => 100]);
        PeriodicPerformance::create(['plan_item_id' => $item->id, 'period' => "$year-$month", 'actual_value' => $actual, 'status' => 'submitted', 'workflow_status' => $state]);
    }

    public function test_selected_reference_year_and_period_comparisons(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'Cargo', 'code' => 'CG', 'reporting_type' => 'monthly']);
        $user = $this->user($department);
        $this->createPerformance($department, $user, 2024, 'july', 50);
        $this->createPerformance($department, $user, 2026, 'july', 80);
        $this->createPerformance($department, $user, 2026, 'august', 90);
        $year = $this->actingAs($user)->get(route('performance.comparison', ['performance_year' => 2026, 'reference_year' => 2024, 'performance_period' => 'july']))->assertOk()->viewData('comparison');
        $this->assertSame(30.0, $year['delta']);
        $period = $this->get(route('performance.comparison', ['comparison_method' => 'period', 'performance_year' => 2026, 'performance_period' => 'august', 'reference_period' => 'july']))->assertOk()->viewData('comparison');
        $this->assertSame(10.0, $period['delta']);
    }

    public function test_department_and_uncapped_target_comparisons(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $first = Department::create(['name' => 'Cargo A', 'code' => 'CA', 'reporting_type' => 'monthly']);
        $second = Department::create(['name' => 'Cargo B', 'code' => 'CB', 'reporting_type' => 'monthly']);
        $user = $this->user($first);
        $this->createPerformance($first, $user, 2026, 'july', 120);
        $this->createPerformance($second, $user, 2026, 'july', 60);
        $this->createPerformance($first, $user, 2025, 'july', 60);
        // Department mode now requires a reconciled annual schedule, not only a monthly target.
        PlanItem::query()->update(['annual_target' => 100]);
        $result = $this->actingAs($user)->get(route('performance.comparison', ['comparison_method' => 'department', 'performance_year' => 2026, 'reference_source' => 'plan:2025', 'performance_period' => 'july', 'performance_department' => $first->id]))->assertOk()->viewData('comparison');
        $this->assertSame(40.0, $result['delta']);
        $target = $this->get(route('performance.comparison', ['comparison_method' => 'target', 'performance_year' => 2026, 'performance_period' => 'july', 'performance_department' => $first->id]))->assertOk()->viewData('comparison');
        $this->assertSame(120.0, $target['currentAverage']);
        $this->assertSame(20.0, $target['delta']);
    }

    public function test_provisional_results_missing_comparisons_and_department_scope(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $own = Department::create(['name' => 'Own', 'code' => 'OW', 'reporting_type' => 'monthly']);
        $other = Department::create(['name' => 'Other', 'code' => 'OT', 'reporting_type' => 'monthly']);
        $user = $this->user($own, 'department_chief');
        $this->createPerformance($own, $user, 2026, 'july', 70, 'submitted_to_plan_report_department');
        // Exercise the retained recorded-period provider explicitly: the default
        // route now opens the complete Master annual builder, including pending indicators.
        $official = $this->actingAs($user)->get(route('performance.comparison', ['comparison_method' => 'year']))->assertOk()->viewData('comparison');
        $this->assertCount(0, $official['rows']);
        $reported = $this->get(route('performance.comparison', ['comparison_method' => 'year', 'result_scope' => 'reported']))->assertOk()->assertSee('Provisional comparison.')->viewData('comparison');
        $this->assertCount(1, $reported['rows']);
        $this->assertNull($reported['rows'][0]['reference']);
        $this->assertNull($reported['rows'][0]['delta']);
        $this->get(route('performance.comparison', ['performance_department' => $other->id]))->assertSessionHasErrors('performance_department');
        $this->get(route('performance.comparison', ['performance_period' => 'invalid']))->assertSessionHasErrors('performance_period');
    }

    public function test_year_comparison_does_not_pair_different_months(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'Cargo', 'code' => 'CG', 'reporting_type' => 'monthly']);
        $user = $this->user($department);
        $this->createPerformance($department, $user, 2025, 'july', 50);
        $this->createPerformance($department, $user, 2026, 'august', 90);
        $comparison = $this->actingAs($user)->get(route('performance.comparison', ['performance_year' => 2026]))->assertOk()->viewData('comparison');
        $this->assertSame(0, $comparison['matched']);
        $this->assertSame(1, $comparison['unmatched']);
        $this->assertNull($comparison['delta']);
    }

    public function test_weekly_values_are_aggregated_before_monthly_target_comparison(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'Cargo', 'code' => 'CG', 'reporting_type' => 'weekly']);
        $user = $this->user($department);
        $objective = StrategicObjective::create(['code' => '1', 'department_id' => $department->id, 'description' => 'Cargo', 'weight' => 10, 'unit' => 'Tons', 'status' => 'approved', 'target_distribution' => 'sum']);
        $plan = Plan::create(['department_id' => $department->id, 'year' => 2026, 'title' => 'Cargo plan', 'type' => 'annual', 'status' => 'approved', 'created_by' => $user->id]);
        $item = PlanItem::create(['plan_id' => $plan->id, 'strategic_objective_id' => $objective->id, 'july_target' => 100]);
        foreach ([40, 80] as $index => $actual) {
            \App\Models\WeeklyPerformance::create(['plan_item_id' => $item->id, 'year' => 2026, 'month' => 'july', 'week_number' => $index + 1, 'planned_value' => 50, 'actual_value' => $actual, 'percentage' => $actual * 2, 'workflow_status' => 'approved_by_head_office']);
        }
        $comparison = $this->actingAs($user)->get(route('performance.comparison', ['comparison_method' => 'target', 'performance_year' => 2026, 'performance_period' => 'july', 'reporting_frequency' => 'weekly']))->assertOk()->viewData('comparison');
        $this->assertSame(120.0, $comparison['currentAverage']);
        $this->assertSame(120.0, $comparison['rows'][0]['actual']);
    }

    public function test_period_view_defaults_to_the_latest_two_distinct_recorded_months(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'Cargo', 'code' => 'CG', 'reporting_type' => 'monthly']);
        $user = $this->user($department);
        $this->createPerformance($department, $user, 2026, 'september', 70);
        $this->createPerformance($department, $user, 2026, 'october', 40);
        $page = $this->actingAs($user)->get(route('performance.comparison', ['comparison_method' => 'period']))->assertOk();
        $result = $page->viewData('comparison');
        $this->assertSame('october', $result['period']);
        $this->assertSame('september', $result['referencePeriod']);
        $this->assertSame(40.0, $result['currentAverage']);
        $this->assertSame(70.0, $result['referenceAverage']);
        $this->assertSame(-30.0, $result['delta']);
        $page->assertSee('Period-over-Period Comparison')->assertSee('Master overview')->assertDontSee('name="reference_year"', false);
    }

    public function test_period_view_with_one_recorded_month_does_not_compare_it_to_itself(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'Cargo', 'code' => 'CG', 'reporting_type' => 'monthly']);
        $user = $this->user($department);
        $this->createPerformance($department, $user, 2026, 'july', 70);
        $result = $this->actingAs($user)->get(route('performance.comparison', ['comparison_method' => 'period']))->assertOk()->viewData('comparison');
        $this->assertSame('july', $result['period']);
        $this->assertNull($result['referencePeriod']);
        $this->assertNull($result['delta']);
        $this->assertSame(0, $result['matched']);
    }

    public function test_period_view_rejects_self_comparisons_and_overlapping_all_results(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'Cargo', 'code' => 'CG', 'reporting_type' => 'monthly']);
        $user = $this->user($department);
        $this->actingAs($user)->get(route('performance.comparison', ['comparison_method' => 'period', 'performance_period' => 'july', 'reference_period' => 'july']))->assertSessionHasErrors('reference_period');
        $this->get(route('performance.comparison', ['comparison_method' => 'period', 'performance_period' => 'all', 'reference_period' => 'all']))->assertSessionHasErrors('performance_period');
        $this->get(route('performance.comparison', ['comparison_method' => 'period', 'performance_period' => 'q1', 'reference_period' => 'august']))->assertSessionHasErrors('reference_period');
    }

    public function test_period_current_selection_automatically_chooses_an_earlier_distinct_reference(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'Cargo', 'code' => 'CG', 'reporting_type' => 'monthly']);
        $user = $this->user($department);
        $this->createPerformance($department, $user, 2026, 'july', 60);
        $this->createPerformance($department, $user, 2026, 'august', 80);
        $result = $this->actingAs($user)->get(route('performance.comparison', ['comparison_method' => 'period', 'performance_period' => 'august']))->assertOk()->viewData('comparison');
        $this->assertSame('july', $result['referencePeriod']);
        $this->assertSame(20.0, $result['delta']);
    }

    public function test_department_view_selects_one_department_across_years_and_rejects_different_departments(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $a = Department::create(['name' => 'Cargo A', 'code' => 'CA', 'reporting_type' => 'monthly']);
        $b = Department::create(['name' => 'Cargo B', 'code' => 'CB', 'reporting_type' => 'monthly']);
        $user = $this->user($a);
        $this->createPerformance($a, $user, 2026, 'july', 80);
        $this->createPerformance($b, $user, 2026, 'july', 60);
        $this->createPerformance($a, $user, 2025, 'july', 70);
        PlanItem::query()->update(['annual_target' => 100]);
        $result = $this->actingAs($user)->get(route('performance.comparison', ['comparison_method' => 'department']))->assertOk()->viewData('comparison');
        $this->assertCount(1, $result['departmentRows']);
        $this->assertSame($a->id, $result['referenceCard']['id']);
        $this->assertSame($a->id, $result['currentCard']['id']);
        $this->assertSame(10.0, $result['delta']);
        $this->get(route('performance.comparison', ['comparison_method' => 'department', 'performance_department' => $a->id, 'reference_department' => $b->id]))->assertSessionHasErrors('reference_department');
    }

    public function test_explicit_quarter_uses_an_earlier_quarter_instead_of_a_month(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'Cargo', 'code' => 'CG', 'reporting_type' => 'monthly']);
        $user = $this->user($department);
        $this->createPerformance($department, $user, 2026, 'september', 70);
        $this->createPerformance($department, $user, 2026, 'october', 40);
        $result = $this->actingAs($user)->get(route('performance.comparison', ['comparison_method' => 'period', 'performance_period' => 'q2']))->assertOk()->viewData('comparison');
        $this->assertSame('q1', $result['referencePeriod']);
        $this->assertSame(-30.0, $result['delta']);
    }

    public function test_year_view_does_not_compare_a_year_with_itself(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'Cargo', 'code' => 'CG', 'reporting_type' => 'monthly']);
        $user = $this->user($department);
        $this->actingAs($user)->get(route('performance.comparison', ['comparison_method' => 'year', 'performance_year' => 2026, 'reference_year' => 2026]))->assertSessionHasErrors('reference_year');
    }

    public function test_switching_review_scope_preserves_the_selected_period_pair(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name' => 'Cargo', 'code' => 'CG', 'reporting_type' => 'monthly']);
        $user = $this->user($department);
        $this->createPerformance($department, $user, 2026, 'july', 60);
        $this->createPerformance($department, $user, 2026, 'august', 80);
        $this->createPerformance($department, $user, 2026, 'october', 90);
        $page = $this->actingAs($user)->get(route('performance.comparison', ['comparison_method' => 'period', 'performance_period' => 'august', 'reference_period' => 'july']))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($page->getContent());
        $url = (new \DOMXPath($document))->query('//div[contains(@class,"pc-view-context")]/a')->item(0)->getAttribute('href');
        $result = $this->get($url)->assertOk()->viewData('comparison');
        $this->assertSame('reported', $result['scope']);
        $this->assertSame('august', $result['period']);
        $this->assertSame('july', $result['referencePeriod']);
    }
}
