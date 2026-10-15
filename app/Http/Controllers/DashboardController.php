<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Department;
use App\Models\StrategicObjective;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\PeriodicPerformance;
use App\Models\WeeklyPerformance;
use App\Models\Employee;
use App\Models\User;
use App\Models\Task;
use App\Models\TaskReport;
use App\Models\AuditLog;
use App\Models\EdrReport;
use App\Services\ScoreCalculator;
use App\Services\PerformanceLevel;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    protected $scoreCalculator;

    public function __construct(ScoreCalculator $scoreCalculator)
    {
        $this->scoreCalculator = $scoreCalculator;
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $employee = $user->employee;
        // Some workflow accounts are linked directly to a department without an
        // employee record. Keep their dashboard scoped to that department too.
        $dashboardEmployee = $employee ?: (object) [
            'id' => null,
            'department_id' => $user->department_id,
        ];
        $data = [];

        if ($user->hasRole('system_admin')) {
            $data = $this->getAdminData();
        } elseif ($user->hasRole('hr_manager')) {
            $data = $this->getHRData();
        } elseif ($user->hasRole('auditor')) {
            $data = $this->getAuditorData();
        } elseif ($user->isDepartmentChief()) {
            $data = ['role' => 'department_chief'];
        } elseif ($user->isDepartmentDirectorate()) {
            $data = ['role' => 'department_directorate'];
        } elseif ($user->isDepartmentExpert()) {
            $data = ['role' => 'department_expert'];
        } elseif ($user->isPlanReportHeadOffice()) {
            $data = ['role' => 'plan_report_head_office'];
        } elseif ($user->isPlanReportDepartmentExpert()) {
            $data = ['role' => 'plan_report_department_expert'];
        } elseif ($user->isPlanReportDirectorate()) {
            $data = ['role' => 'plan_report_directorate'];
        } elseif ($user->isHeadOffice()) {
            $data = ['role' => 'head_office'];
        } elseif ($user->hasRole('dept_head')) {
            $data = ['role' => 'dept_head'];
        } elseif ($user->hasRole('supervisor')) {
            $data = $this->getSupervisorData($dashboardEmployee);
        } else {
            $data = $this->getEmployeeData($dashboardEmployee);
        }

        $primaryRole = $user->roles->firstWhere('name', $data['role']);
        $data['roleLabel'] = $primaryRole && $primaryRole->description
            ? $primaryRole->description
            : ucwords(str_replace('_', ' ', $data['role'] ?? 'employee'));
        $data['workspaceNotice'] = null;
        if ($user->roles->isEmpty()) {
            $data['roleLabel'] = 'No role assigned';
            $data['workspaceNotice'] = 'Your account has no assigned role. Ask the administrator to assign your role so your authorized workspace is available.';
        } elseif (!app(\App\Services\DashboardWorkspace::class)->organizationWide($user) && !$user->department_id && !$employee?->department_id) {
            $data['workspaceNotice'] = 'No department is assigned to this account. Department plans and performance will appear after the administrator assigns your department.';
        } elseif (in_array($data['role'], ['employee', 'supervisor'], true) && !$user->employee_id) {
            $data['workspaceNotice'] = 'This account is not linked to an employee profile. Personal tasks will appear after the administrator links the correct employee profile.';
        }
        $data['department'] = $data['department'] ?? optional($user->department)->name
            ?? optional($employee ? $employee->department : null)->name
            ?? (app(\App\Services\DashboardWorkspace::class)->organizationWide($user) ? 'Organization-wide' : 'No department assigned');
        $data['unreadNotifications'] = $user->unreadNotifications()->count();
        $workspace = app(\App\Services\DashboardWorkspace::class)->snapshot($user, $request->integer('performance_year') ?: null);
        $data['workspaceYear'] = $workspace['year'];
        $data['workspaceDailyRecords'] = $workspace['daily'];
        if ($workspace['pendingItems'] > 0 && !$data['workspaceNotice']) {
            $data['workspaceNotice'] = $workspace['pendingItems'].' department plan items have not yet been approved for reporting. They will appear in your reporting workspace once approved.';
        }
        $data['barChart'] = $workspace['barChart'];
        $data['doughnutChart'] = $workspace['doughnutChart'];
        if ($user->isDepartmentExpert() || $user->isDepartmentDirectorate() || $user->isDepartmentChief()
            || $user->isPlanReportDepartmentExpert() || $user->isPlanReportDirectorate()
            || $user->isPlanReportHeadOffice() || $user->isHeadOffice() || $user->isDeptHead()) {
            $data['stats'] = [
                ['label' => $user->isDepartmentExpert() ? 'Department Plan Items' : 'Approved Plan Items', 'value' => $workspace['items'], 'icon' => 'fas fa-list-alt', 'color' => 'bg-info', 'link' => route('plans.index')],
                ['label' => 'Reported Records', 'value' => $workspace['reported'], 'icon' => 'fas fa-paper-plane', 'color' => 'bg-primary', 'link' => route('performance.index')],
                ['label' => 'Reviewed / Approved Records', 'value' => $workspace['official'], 'icon' => 'fas fa-check-circle', 'color' => 'bg-success', 'link' => route('performance.index')],
                ['label' => $user->isDepartmentExpert() ? 'Drafts / Returned Records' : 'Awaiting Your Review', 'value' => $workspace['queue'], 'icon' => 'fas fa-hourglass-half', 'color' => 'bg-warning', 'link' => route('performance.index')],
            ];
        }
        if ($user->isHRManager()) {
            $employees = Employee::with('department')->get();
            $departments = $employees->countBy(fn ($employee) => $employee->department?->name ?? 'Unassigned');
            $statuses = $employees->countBy('employment_status');
            $data['barChart'] = ['title' => 'Employees by Department', 'hasData' => $employees->isNotEmpty(), 'labels' => $departments->keys()->all(), 'datasets' => [['label' => 'Employees', 'data' => $departments->values()->all(), 'color' => '#07988a']]];
            $data['doughnutChart'] = ['title' => 'Employee Status', 'hasData' => $employees->isNotEmpty(), 'labels' => $statuses->keys()->map(fn ($status) => ucfirst($status))->all(), 'data' => $statuses->values()->all()];
        } elseif (in_array($data['role'], ['employee', 'supervisor'], true)) {
            $tasks = Task::where($data['role'] === 'supervisor' ? 'assigned_by' : 'assigned_to', $user->employee_id ?: 0)->get();
            $statuses = $tasks->countBy('status');
            $data['barChart'] = ['title' => 'Task Completion', 'hasData' => $tasks->isNotEmpty(), 'labels' => $tasks->map(fn ($task) => $task->title)->all(), 'datasets' => [['label' => 'Completion (%)', 'data' => $tasks->pluck('completion_percentage')->all(), 'color' => '#07988a']]];
            $data['doughnutChart'] = ['title' => 'Task Status', 'hasData' => $tasks->isNotEmpty(), 'labels' => $statuses->keys()->map(fn ($status) => ucwords(str_replace('_', ' ', $status)))->all(), 'data' => $statuses->values()->all()];
        }
        $data['quickActions'] = $this->quickActionsFor($user);
        $data['performanceAnalytics'] = $user->hasPermission('view_performance')
            ? $this->buildPerformanceAnalytics($user, $request)
            : null;
        $data['attentionItems'] = collect($data['stats'] ?? [])
            ->filter(fn ($stat) => in_array($stat['color'] ?? '', ['bg-warning', 'bg-danger'], true) && (float) $stat['value'] > 0)
            ->map(fn ($stat) => [
                'label' => $stat['label'],
                'value' => $stat['value'],
                'icon' => $stat['icon'],
                'link' => $stat['link'],
                'tone' => ($stat['color'] ?? '') === 'bg-danger' ? 'danger' : 'warning',
            ])->values();

        // Ordinary users see only their own audit history. System-wide logs are
        // reserved for roles that are explicitly responsible for audit oversight.
        $activityQuery = AuditLog::with('user')->latest();
        if (!$user->isSystemAdmin() && !$user->hasRole('auditor')) {
            $activityQuery->where('user_id', $user->id);
        }
        $data['recentActivities'] = $activityQuery
            ->latest()
            ->limit(8)
            ->get()
            ->map(function ($log) {
                return [
                    'actor' => optional($log->user)->name ?? 'System',
                    'action' => $log->action,
                    'time' => $log->created_at->diffForHumans(),
                ];
            });

        return view('dashboard.index', $data);
    }

    public function performanceComparison(Request $request)
    {
        $comparison = app(\App\Services\PerformanceComparison::class)->build($request->user(), $request);
        return view('performance.comparison', compact('comparison'));
    }

    private function buildPerformanceAnalytics(User $user, Request $request): array
    {
        $workspace = app(\App\Services\DashboardWorkspace::class);
        $organizationWide = $workspace->organizationWide($user);
        $years = $workspace->items($user)->join('plans', 'plans.id', '=', 'plan_items.plan_id')->distinct()->orderByDesc('plans.year')->pluck('plans.year')->map(fn ($year) => (int) $year)->values();
        $year = (int) $request->get('performance_year', $years->first() ?? now()->year);
        if (!$years->contains($year)) $year = (int) ($years->first() ?? now()->year);
        $departments = $organizationWide ? Department::where('is_active', true)->orderBy('name')->get(['id','name']) : collect();
        $selectedDepartment = $organizationWide && $request->filled('performance_department') ? (int) $request->performance_department : null;
        if ($selectedDepartment && !$departments->contains('id', $selectedDepartment)) $selectedDepartment = null;
        $departmentIds = $selectedDepartment ? [$selectedDepartment] : ($organizationWide ? Department::pluck('id')->all() : array_filter([(int) ($user->department_id ?? $user->employee?->department_id)]));
        $official = ['reviewed_no_correction_required', 'approved_by_head_office'];

        $weeklyQuery = WeeklyPerformance::with(['planItem.plan.department','planItem.strategicObjective'])
            ->where('year', $year)->whereHas('planItem.plan', fn ($q) => $q->where('status','approved')->whereIn('department_id',$departmentIds));
        $periodicQuery = PeriodicPerformance::with(['planItem.plan.department','planItem.strategicObjective'])
            ->whereHas('planItem.plan', fn ($q) => $q->where('year',$year)->where('status','approved')->whereIn('department_id',$departmentIds));
        $weeklyQuery->whereIn('plan_item_id', $workspace->items($user, $year, $departmentIds)->select('plan_items.id'));
        $periodicQuery->whereIn('plan_item_id', $workspace->items($user, $year, $departmentIds)->select('plan_items.id'));
        $weeklyAll = $weeklyQuery->get(); $periodicAll = $periodicQuery->get();
        if ($user->isHeadOffice() && !$user->isSystemAdmin() && !$user->isPlanReportHeadOffice()) {
            $weeklyAll = $weeklyAll->filter(fn ($entry) => $workspace->isOfficial($entry));
            $periodicAll = $periodicAll->filter(fn ($entry) => $workspace->isOfficial($entry));
        }
        $weekly = $weeklyAll->filter(fn ($entry) => $workspace->isOfficial($entry));
        $periodic = $periodicAll->filter(fn ($entry) => $workspace->isOfficial($entry));

        $rows = collect();
        foreach ($weekly as $entry) {
            if (is_null($entry->percentage)) continue;
            $rows->push(['achievement'=>(float)$entry->percentage,'month'=>strtolower($entry->month),'period'=>strtolower($entry->month),'department'=>$entry->planItem?->plan?->department?->name ?? 'Unknown','objective'=>$entry->planItem?->strategicObjective?->description ?? 'KPI']);
        }
        foreach ($periodic as $entry) {
            $item=$entry->planItem; $period=strtolower((string)$entry->period); $periodParts=explode('-',$period); $part=str_contains($period,'-')?end($periodParts):$period;
            $field=in_array($part,['q1','q2','q3','q4'],true)?$part.'_target':($part==='annual'?'annual_target':$part.'_target');
            $target=$item?->getPlannedTargetForPeriod($part); if(is_null($target)||!$item?->strategicObjective)continue;
            $achievement=app(\App\Services\EdrPerformanceCalculator::class)->achievement((float)$entry->actual_value,(float)$target,$item->strategicObjective->goal_direction??'increase');
            $rows->push(['achievement'=>$achievement,'month'=>in_array($part,['july','august','september','october','november','december','january','february','march','april','may','june'],true)?$part:null,'period'=>$part,'department'=>$item->plan?->department?->name ?? 'Unknown','objective'=>$item->strategicObjective->description]);
        }

        $months=['july','august','september','october','november','december','january','february','march','april','may','june'];
        $periodOptions=['all'=>'All periods','q1'=>'Quarter 1 (Jul–Sep)','q2'=>'Quarter 2 (Oct–Dec)','q3'=>'Quarter 3 (Jan–Mar)','q4'=>'Quarter 4 (Apr–Jun)','annual'=>'Annual','july'=>'July','august'=>'August','september'=>'September','october'=>'October','november'=>'November','december'=>'December','january'=>'January','february'=>'February','march'=>'March','april'=>'April','may'=>'May','june'=>'June'];
        $selectedPeriod=array_key_exists((string)$request->get('performance_period','all'),$periodOptions)?(string)$request->get('performance_period','all'):'all';
        $kpiOptions=$rows->pluck('objective')->filter()->unique()->sort()->values();
        $selectedKpi=$request->filled('performance_kpi')&&$kpiOptions->contains((string)$request->performance_kpi)?(string)$request->performance_kpi:null;
        $rows=$this->filterPerformanceRows($rows,$selectedPeriod,$selectedKpi);
        $trend=collect($months)->map(fn($month)=>$rows->where('month',$month)->isEmpty()?null:round(min(100,(float)$rows->where('month',$month)->avg('achievement')),1))->all();
        $byDepartment=$rows->groupBy('department')->map(fn($group)=>round(min(100,(float)$group->avg('achievement')),1))->sortDesc()->take(15);
        $levels=app(PerformanceLevel::class); $bands=['Exceptional'=>0,'High'=>0,'Satisfactory'=>0,'Needs Improvement'=>0,'Unsatisfactory'=>0];
        foreach($rows as $row){$label=$levels->for(min(100,$row['achievement']))['shortLabel'];if(isset($bands[$label]))$bands[$label]++;}
        $byObjective=$rows->groupBy('objective')->map(fn($group)=>round(min(100,(float)$group->avg('achievement')),1))->sortDesc();
        $officialCount=$this->filterPerformanceRows($this->entryFilterRows($weekly->concat($periodic)),$selectedPeriod,$selectedKpi)->count();
        $submittedCount=$this->filterPerformanceRows($this->entryFilterRows($weeklyAll->concat($periodicAll)),$selectedPeriod,$selectedKpi)->count();
        $comparison=$this->buildPreviousPerformanceComparison($user,$year,$departmentIds,$rows,$months,$official,$selectedPeriod,$selectedKpi);

        return [
            'organizationWide'=>$organizationWide,'years'=>$years,'year'=>$year,'departmentOptions'=>$departments,'selectedDepartment'=>$selectedDepartment,
            'periodOptions'=>$periodOptions,'selectedPeriod'=>$selectedPeriod,'kpiOptions'=>$kpiOptions,'selectedKpi'=>$selectedKpi,
            'average'=>$rows->isEmpty()?null:round(min(100,(float)$rows->avg('achievement')),1),'coverage'=>$submittedCount?round($officialCount/$submittedCount*100,1):0,
            'officialRecords'=>$officialCount,'submittedRecords'=>$submittedCount,
            'trend'=>['labels'=>array_map('ucfirst',$months),'data'=>$trend],
            'departments'=>['labels'=>$byDepartment->keys()->values(),'data'=>$byDepartment->values()],
            'bands'=>['labels'=>array_keys($bands),'data'=>array_values($bands)],
            'ranking'=>['labels'=>$byObjective->take(8)->keys()->map(fn($v)=>mb_strimwidth($v,0,42,'…'))->values(),'data'=>$byObjective->take(8)->values()],
            'comparison'=>$comparison,
            'hasData'=>$rows->isNotEmpty(),
        ];
    }

    private function buildPreviousPerformanceComparison(User $user, int $year, array $departmentIds, $currentRows, array $months, array $official, string $selectedPeriod='all', ?string $selectedKpi=null): array
    {
        $previousYear=$year-1;
        $weeklyQuery=WeeklyPerformance::with(['planItem.plan.department','planItem.strategicObjective'])->where('year',$previousYear)->where(function ($q) use ($official) { $q->whereIn('workflow_status', $official)->orWhere(fn ($legacy) => $legacy->where('status', 'approved')->where(fn ($state) => $state->whereNull('workflow_status')->orWhere('workflow_status', '')->orWhere('workflow_status', 'approved'))); })->whereHas('planItem.plan',fn($q)=>$q->where('year',$previousYear)->where('status','approved')->whereIn('department_id',$departmentIds));
        $periodicQuery=PeriodicPerformance::with(['planItem.plan.department','planItem.strategicObjective'])->where(function ($q) use ($official) { $q->whereIn('workflow_status', $official)->orWhere(fn ($legacy) => $legacy->where('status', 'approved')->where(fn ($state) => $state->whereNull('workflow_status')->orWhere('workflow_status', '')->orWhere('workflow_status', 'approved'))); })->whereHas('planItem.plan',fn($q)=>$q->where('year',$previousYear)->where('status','approved')->whereIn('department_id',$departmentIds));
        $workspace = app(\App\Services\DashboardWorkspace::class);
        $weeklyQuery->whereIn('plan_item_id', $workspace->items($user, $previousYear, $departmentIds)->select('plan_items.id'));
        $periodicQuery->whereIn('plan_item_id', $workspace->items($user, $previousYear, $departmentIds)->select('plan_items.id'));
        $previousRows=collect();
        foreach($weeklyQuery->get() as $entry){if(is_null($entry->percentage))continue;$previousRows->push(['achievement'=>(float)$entry->percentage,'month'=>strtolower($entry->month),'period'=>strtolower($entry->month),'department'=>$entry->planItem?->plan?->department?->name??'Unknown','objective'=>$entry->planItem?->strategicObjective?->description??'KPI']);}
        foreach($periodicQuery->get() as $entry){$item=$entry->planItem;$parts=explode('-',strtolower((string)$entry->period));$part=end($parts);$field=in_array($part,['q1','q2','q3','q4'],true)?$part.'_target':($part==='annual'?'annual_target':$part.'_target');$target=$item?->getPlannedTargetForPeriod($part);if(is_null($target)||!$item?->strategicObjective)continue;$previousRows->push(['achievement'=>app(\App\Services\EdrPerformanceCalculator::class)->achievement((float)$entry->actual_value,(float)$target,$item->strategicObjective->goal_direction??'increase'),'month'=>in_array($part,$months,true)?$part:null,'period'=>$part,'department'=>$item->plan?->department?->name??'Unknown','objective'=>$item->strategicObjective->description]);}
        $previousRows=$this->filterPerformanceRows($previousRows,$selectedPeriod,$selectedKpi);
        $currentAverage=$currentRows->isEmpty()?null:round(min(100,(float)$currentRows->avg('achievement')),1);$previousAverage=$previousRows->isEmpty()?null:round(min(100,(float)$previousRows->avg('achievement')),1);$delta=is_null($currentAverage)||is_null($previousAverage)?null:round($currentAverage-$previousAverage,1);
        $currentMonthly=collect($months)->map(fn($month)=>$currentRows->where('month',$month)->isEmpty()?null:round(min(100,(float)$currentRows->where('month',$month)->avg('achievement')),1));$previousMonthly=collect($months)->map(fn($month)=>$previousRows->where('month',$month)->isEmpty()?null:round(min(100,(float)$previousRows->where('month',$month)->avg('achievement')),1));
        $objectiveNames=$currentRows->pluck('objective')->intersect($previousRows->pluck('objective'))->unique();$kpiChanges=$objectiveNames->map(function($objective)use($currentRows,$previousRows){$current=round(min(100,(float)$currentRows->where('objective',$objective)->avg('achievement')),1);$previous=round(min(100,(float)$previousRows->where('objective',$objective)->avg('achievement')),1);return ['label'=>mb_strimwidth($objective,0,42,'…'),'current'=>$current,'previous'=>$previous,'delta'=>round($current-$previous,1)];})->sortByDesc(fn($row)=>abs($row['delta']))->take(10)->values();
        $departmentNames=$currentRows->pluck('department')->merge($previousRows->pluck('department'))->unique();$departmentChanges=$departmentNames->map(function($department)use($currentRows,$previousRows){$current=$currentRows->where('department',$department);$previous=$previousRows->where('department',$department);return ['label'=>$department,'current'=>$current->isEmpty()?null:round(min(100,(float)$current->avg('achievement')),1),'previous'=>$previous->isEmpty()?null:round(min(100,(float)$previous->avg('achievement')),1)];})->take(15)->values();
        return ['currentYear'=>$year,'previousYear'=>$previousYear,'currentAverage'=>$currentAverage,'previousAverage'=>$previousAverage,'delta'=>$delta,'direction'=>is_null($delta)?'unavailable':($delta>0?'improved':($delta<0?'declined':'unchanged')),'hasPrevious'=>$previousRows->isNotEmpty(),'trend'=>['labels'=>array_map('ucfirst',$months),'current'=>$currentMonthly->all(),'previous'=>$previousMonthly->all()],'kpis'=>['labels'=>$kpiChanges->pluck('label'),'current'=>$kpiChanges->pluck('current'),'previous'=>$kpiChanges->pluck('previous'),'deltas'=>$kpiChanges->pluck('delta')],'departments'=>['labels'=>$departmentChanges->pluck('label'),'current'=>$departmentChanges->pluck('current'),'previous'=>$departmentChanges->pluck('previous')]];
    }

    private function filterPerformanceRows($rows, string $period, ?string $kpi)
    {
        $quarters=['q1'=>['july','august','september'],'q2'=>['october','november','december'],'q3'=>['january','february','march'],'q4'=>['april','may','june']];
        if($period!=='all'){$rows=$rows->filter(function($row)use($period,$quarters){if(isset($quarters[$period]))return ($row['period']??null)===$period||in_array($row['month']??null,$quarters[$period],true);return ($row['period']??null)===$period||($row['month']??null)===$period;});}
        if($kpi)$rows=$rows->where('objective',$kpi);
        return $rows->values();
    }

    private function entryFilterRows($entries)
    {
        $months = ['july','august','september','october','november','december','january','february','march','april','may','june'];
        return $entries->map(function ($entry) use ($months) {
            $period = $entry instanceof WeeklyPerformance ? strtolower($entry->month) : preg_replace('/^\d{4}-/', '', strtolower($entry->period));
            return ['period' => $period, 'month' => in_array($period, $months, true) ? $period : null, 'objective' => $entry->planItem?->strategicObjective?->description];
        });
    }

    private function quickActionsFor(User $user): array
    {
        $actions = [];
        $add = function (bool $allowed, string $label, string $route, string $icon, string $tone = 'primary') use (&$actions) {
            if ($allowed) {
                $actions[] = compact('label', 'route', 'icon', 'tone');
            }
        };

        $add($user->hasPermission('view_objectives'), 'View objectives', 'objectives.index', 'fas fa-bullseye', 'primary');
        $add($user->hasPermission('manage_objectives'), 'Create objective', 'objectives.create', 'fas fa-plus-circle', 'success');
        $add($user->hasPermission('view_performance'), 'Performance workspace', 'performance.index', 'fas fa-chart-line', 'info');
        $add($user->canAccessReportingModule('weekly'), 'Weekly workbench', 'weekly.dashboard', 'fas fa-calendar-week', 'warning');
        $add($user->canAccessReportingModule('monthly'), 'Monthly reports', 'monthly.dashboard', 'fas fa-calendar-alt', 'info');
        $add($user->hasPermission('view_reports'), 'Reports', 'reports.index', 'fas fa-file-alt', 'primary');
        $add($user->hasPermission('manage_own_tasks'), 'My tasks', 'tasks.my', 'fas fa-tasks', 'success');
        $add($user->hasPermission('manage_settings'), 'System settings', 'settings.index', 'fas fa-cog', 'dark');
        $add($user->hasPermission('view_audit_logs'), 'Audit logs', 'audit.index', 'fas fa-history', 'danger');

        return array_slice($actions, 0, 6);
    }

    // ─── Stats-only helpers (unchanged) ───────────────────────────────────────

    private function getAdminData()
    {
        $charts = $this->buildOrgWideCharts();
        return [
            'role'   => 'system_admin',
            'stats'  => [
                ['label' => 'Total Departments', 'value' => Department::count(),             'icon' => 'fas fa-building',      'color' => 'bg-info',    'link' => route('departments.index')],
                ['label' => 'Objectives',         'value' => StrategicObjective::count(),    'icon' => 'fas fa-bullseye',      'color' => 'bg-success', 'link' => route('objectives.index')],
                ['label' => 'Users & RBAC',       'value' => User::count(),                  'icon' => 'fas fa-user-shield',   'color' => 'bg-warning', 'link' => route('users.index')],
                ['label' => 'Audit Trails',       'value' => AuditLog::count(),              'icon' => 'fas fa-history',       'color' => 'bg-danger',  'link' => route('audit.index')],
            ],
            'barChart'      => $charts['barChart'],
            'doughnutChart' => $charts['doughnutChart'],
            'lineChart'     => $charts['lineChart'],
        ];
    }

    private function getHRData()
    {
        $charts = $this->buildOrgWideCharts();
        return [
            'role'   => 'hr_manager',
            'stats'  => [
                ['label' => 'Active Employees', 'value' => Employee::where('employment_status', 'active')->count(), 'icon' => 'fas fa-user-check',    'color' => 'bg-success', 'link' => route('employees.index')],
                ['label' => 'Total Employees',  'value' => Employee::count(),                                        'icon' => 'fas fa-users',          'color' => 'bg-info',    'link' => route('employees.index')],
                ['label' => 'Departments',      'value' => Department::count(),         'icon' => 'fas fa-building',  'color' => 'bg-primary', 'link' => route('departments.index')],
                ['label' => 'Inactive Employees',   'value' => Employee::where('employment_status', '!=', 'active')->count(),        'icon' => 'fas fa-user-clock', 'color' => 'bg-warning', 'link' => route('employees.index')],
            ],
            'barChart'      => $charts['barChart'],
            'doughnutChart' => $charts['doughnutChart'],
            'lineChart'     => $charts['lineChart'],
        ];
    }

    private function getAuditorData()
    {
        $charts = $this->buildOrgWideCharts();
        return [
            'role'   => 'auditor',
            'stats'  => [
                ['label' => 'Compliance Logs',  'value' => AuditLog::count(),                            'icon' => 'fas fa-shield-alt',       'color' => 'bg-danger',  'link' => route('audit.index')],
                ['label' => 'Approved Reports', 'value' => EdrReport::where('status', 'approved')->count(),'icon' => 'fas fa-check-circle',   'color' => 'bg-success', 'link' => route('reports.index')],
                ['label' => 'Approved Plans',   'value' => Plan::where('status', 'approved')->count(),    'icon' => 'fas fa-clipboard-check', 'color' => 'bg-info',    'link' => route('plans.index')],
                ['label' => 'Employee Base',    'value' => Employee::count(),                              'icon' => 'fas fa-users',           'color' => 'bg-primary', 'link' => route('employees.index')],
            ],
            'barChart'      => $charts['barChart'],
            'doughnutChart' => $charts['doughnutChart'],
            'lineChart'     => $charts['lineChart'],
        ];
    }

    private function getSupervisorData($employee)
    {
        $employeeId = $employee?->id ?: 0;
        $deptId     = $employee ? $employee->department_id : null;
        $charts     = $this->buildDepartmentCharts($deptId, includeChildren: false);

        return [
            'role'   => 'supervisor',
            'stats'  => [
                ['label' => 'Pending Approvals', 'value' => TaskReport::where('status', 'submitted')->whereHas('task', fn($q) => $q->where('assigned_by', $employeeId))->count(), 'icon' => 'fas fa-check-double',      'color' => 'bg-warning', 'link' => route('tasks.index')],
                ['label' => 'Active Team Tasks', 'value' => Task::where('assigned_by', $employeeId)->where('status', 'in_progress')->count(),                                      'icon' => 'fas fa-hourglass-half',    'color' => 'bg-info',    'link' => route('tasks.index')],
                ['label' => 'My Assignments',    'value' => Task::where('assigned_by', $employeeId)->count(),                                                                       'icon' => 'fas fa-tasks',             'color' => 'bg-success', 'link' => route('tasks.index')],
                ['label' => 'Delayed Tasks',     'value' => Task::where('assigned_by', $employeeId)->where('due_date', '<', now())->where('status', '!=', 'completed')->count(),   'icon' => 'fas fa-exclamation-triangle','color' => 'bg-danger', 'link' => route('tasks.index')],
            ],
            'barChart'      => $charts['barChart'],
            'doughnutChart' => $charts['doughnutChart'],
        ];
    }

    private function getEmployeeData($employee)
    {
        $employeeId = $employee?->id ?: 0;
        $tasks      = Task::where('assigned_to', $employeeId)->get();
        $charts     = $this->buildExpertCharts($employeeId);

        return [
            'role'   => 'employee',
            'stats'  => [
                ['label' => 'My Total Tasks',  'value' => $tasks->count(),                                                                                       'icon' => 'fas fa-tasks',        'color' => 'bg-info',    'link' => route('tasks.my')],
                ['label' => 'Avg Completion',  'value' => $tasks->isEmpty() ? 'N/A' : round($tasks->avg('completion_percentage') ?? 0, 1) . '%',                                             'icon' => 'fas fa-percentage',   'color' => 'bg-success', 'link' => route('tasks.my')],
                ['label' => 'Active Reports',  'value' => TaskReport::whereHas('task', fn($q) => $q->where('assigned_to', $employeeId))->where('status', 'pending')->count(), 'icon' => 'fas fa-file-alt', 'color' => 'bg-warning', 'link' => route('tasks.my')],
                ['label' => 'Due Today',       'value' => Task::where('assigned_to', $employeeId)->whereDate('due_date', today())->count(),                      'icon' => 'fas fa-calendar-day', 'color' => 'bg-danger',  'link' => route('tasks.my')],
            ],
            'barChart'      => $charts['barChart'],
            'doughnutChart' => $charts['doughnutChart'],
        ];
    }

    // ─── New role methods ──────────────────────────────────────────────────────

    private function buildExpertCharts(?int $employeeId): array
    {
        return $this->workspaceCharts();
    }

    private function buildDepartmentCharts(?int $deptId, bool $includeChildren = false): array
    {
        return $this->workspaceCharts();
    }

    private function buildOrgWideCharts(): array
    {
        return $this->workspaceCharts() + ['lineChart' => null];
    }

    private function workspaceCharts(): array
    {
        $workspace = app(\App\Services\DashboardWorkspace::class)->snapshot(auth()->user(), request()->integer('performance_year') ?: null);
        return ['barChart' => $workspace['barChart'], 'doughnutChart' => $workspace['doughnutChart']];
    }

}
