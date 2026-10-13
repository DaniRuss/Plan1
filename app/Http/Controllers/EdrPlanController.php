<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EdrPlanDocument;
use App\Models\Department;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\StrategicObjective;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\WorkflowNotificationService;
use App\Services\OrganizationPlanWordExporter;

class EdrPlanController extends Controller
{
    private const MONTHS = ['july','august','september','october','november','december','january','february','march','april','may','june'];

    public function __construct(private WorkflowNotificationService $notifications) {}

    public function editOrganizationNarrative(Request $request)
    {
        $this->authorizeOrganizationNarrative();
        $years = \App\Services\CalendarHelper::getAppYears();
        $defaultYear = (int) (Plan::where('status', 'approved')->max('year') ?? now()->year);
        $year = (int) $request->get('year', $defaultYear);
        abort_unless(in_array($year, $years, true), 404);
        $narrative = EdrPlanDocument::firstOrNew(['year' => $year]);
        $approvedPlans = Plan::where('year', $year)->where('status', 'approved')->count();
        $approvedItems = PlanItem::whereHas('plan', fn ($q) => $q->where('year', $year)->where('status', 'approved'))->count();
        return view('edr_plans.organization_narrative', compact('year', 'years', 'narrative', 'approvedPlans', 'approvedItems'));
    }

    public function updateOrganizationNarrative(Request $request)
    {
        $year = (int) $request->input('year');
        $document = EdrPlanDocument::firstOrNew(['year' => $year]);
        $this->authorizeNarrativeEdit($document);
        $data = $request->validate([
            'year' => 'required|integer|in:' . implode(',', \App\Services\CalendarHelper::getAppYears()),
            'introduction' => 'nullable|string|max:20000', 'past_performance' => 'nullable|string|max:30000',
            'strategic_issues' => 'nullable|string|max:20000', 'implementation_strategies' => 'nullable|string|max:20000',
            'monitoring_evaluation' => 'nullable|string|max:20000', 'challenges_solutions' => 'nullable|string|max:20000',
            'workflow_action' => 'nullable|in:save,save_and_preview,submit_to_directorate,send_to_head,finalize',
            'word_file' => 'nullable|file|mimes:doc,docx|extensions:doc,docx|max:10240',
        ]);
        $action = $data['workflow_action'] ?? 'save';
        unset($data['workflow_action']);
        unset($data['word_file']);
        if (!$document->exists) $data['prepared_by'] = auth()->id();
        if ($action === 'submit_to_directorate') {
            abort_unless(auth()->user()->isPlanReportDepartmentExpert() && (!$document->exists || $document->status === 'draft'), 403);
            $data['status'] = 'submitted_to_plan_report_directorate';
        } elseif ($action === 'send_to_head') {
            abort_unless(auth()->user()->isPlanReportDirectorate() && $document->status === 'submitted_to_plan_report_directorate', 403);
            $data += ['status' => 'submitted_to_plan_report_head', 'directorate_reviewed_by' => auth()->id(), 'directorate_reviewed_at' => now()];
        } elseif ($action === 'finalize') {
            abort_unless(auth()->user()->isPlanReportHeadOffice() && $document->status === 'submitted_to_plan_report_head', 403);
            $data += ['status' => 'approved', 'head_approved_by' => auth()->id(), 'head_approved_at' => now()];
        }
        $oldPath = $document->word_file_path;
        $newPath = null;
        if ($request->hasFile('word_file')) {
            $newPath = $request->file('word_file')->store("plan_narration/{$year}", 'local');
            $data['word_file_path'] = $newPath;
            $data['word_file_name'] = $request->file('word_file')->getClientOriginalName();
        }
        try {
            $document->fill($data)->save();
        } catch (\Throwable $exception) {
            if ($newPath) Storage::disk('local')->delete($newPath);
            throw $exception;
        }
        if ($newPath && $oldPath) Storage::disk('local')->delete($oldPath);
        if ($action === 'submit_to_directorate') $this->notifications->roles('plan_report_directorate', 'Plan narration awaiting review', "The {$document->year} plan narration was sent for Directorate review.", route('objectives.plan-narrative.edit', ['year'=>$document->year]), 'info', null, auth()->user());
        if ($action === 'send_to_head') $this->notifications->roles('plan_report_head_office', 'Plan narration awaiting approval', "The {$document->year} plan narration was sent for final approval.", route('objectives.plan-narrative.edit', ['year'=>$document->year]), 'info', null, auth()->user());
        AuditLog::log('updated organization EDR plan narration', $document, "Prepared organization narration for {$data['year']}");
        if ($action === 'save_and_preview') {
            return redirect()->route('objectives.download-approved', ['year' => $document->year, 'preview' => 1]);
        }
        return redirect()->route('objectives.plan-narrative.edit', ['year' => $data['year']])->with('success', 'Plan narration saved. Status: ' . str_replace('_', ' ', $document->status) . '.');
    }

    public function downloadPlan(Plan $plan)
    {
        $this->authorizePlanView($plan);
        abort_unless($plan->status === 'approved', 404);
        $plan->load(['department', 'items.strategicObjective.leadDepartment']);
        return $this->planPdf($plan, $plan->items, 'EDR_Plan_' . $this->safeName($plan->department->name) . '_' . $plan->year . '.pdf');
    }

    public function downloadPlanObjective(Plan $plan, StrategicObjective $objective)
    {
        $this->authorizePlanView($plan);
        abort_unless($plan->status === 'approved', 404);
        $objective->load('children.children.children');
        $ids = collect([$objective->id])
            ->merge($objective->children->pluck('id'))
            ->merge($objective->children->flatMap->children->pluck('id'));
        $items = $plan->items()->with(['strategicObjective.leadDepartment'])
            ->whereIn('strategic_objective_id', $ids)->get();
        abort_if($items->isEmpty(), 404);
        $plan->load('department');
        return $this->planPdf($plan, $items, 'EDR_Objective_' . $this->safeName($objective->code) . '_' . $plan->year . '.pdf', $objective);
    }

    public function downloadObjectives(Request $request)
    {
        $user = auth()->user();
        $accessiblePlans = Plan::where('status', 'approved');
        if (!$user->hasOrganizationWidePerformanceAccess() && !$user->hasPermission('view_all_plans')) {
            $accessiblePlans->where('department_id', $user->department_id);
        }
        $defaultYear = (int) ($accessiblePlans->max('year') ?? now()->year);
        $year = (int) $request->get('year', $defaultYear);
        abort_unless(in_array($year, \App\Services\CalendarHelper::getAppYears(), true), 404);
        $plans = Plan::with(['department', 'items.strategicObjective.leadDepartment'])
            ->where('year', $year)->where('status', 'approved');
        if (!$user->hasOrganizationWidePerformanceAccess() && !$user->hasPermission('view_all_plans')) {
            $plans->where('department_id', $user->department_id);
        }
        $plans = $plans->orderBy('department_id')->get();
        if ($plans->isEmpty()) {
            return redirect()->route('objectives.index')
                ->with('error', "No approved plans exist for {$year} EFY.");
        }
        // Resolve the organization narration by the exact selected plan year.
        // Using the latest saved row also protects upgraded databases that may
        // still contain duplicate legacy rows for the same year.
        $narrative = EdrPlanDocument::with('preparer')
            ->where('year', $year)
            ->latest('updated_at')
            ->latest('id')
            ->first();
        $departmentNames = Department::whereIn('id', $plans->pluck('items')->flatten()->flatMap(fn ($item) => $item->performers ?? [])->unique())->pluck('name', 'id');
        $preview = $request->boolean('preview');
        $view = $preview ? 'edr_plans.organization_plan_pdf' : 'edr_plans.organization_approved_plans_pdf';
        $pdf = Pdf::loadView($view, compact('plans', 'narrative', 'year', 'departmentNames'))
            ->setPaper('a4', 'landscape');
        $response = $preview
            ? $pdf->stream("Organization_EDR_Plan_{$year}_Narration_Preview.pdf")
            : $pdf->download("Approved_Organization_Plans_{$year}.pdf");

        return $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function downloadOrganizationNarrativeWord(Request $request, OrganizationPlanWordExporter $exporter)
    {
        $this->authorizeOrganizationNarrative();
        $year = (int) $request->get('year');
        abort_unless(in_array($year, \App\Services\CalendarHelper::getAppYears(), true), 404);

        $plans = Plan::with(['department', 'items.strategicObjective.leadDepartment'])
            ->where('year', $year)
            ->where('status', 'approved')
            ->orderBy('department_id')
            ->get();
        abort_if($plans->isEmpty(), 404, "No approved plans exist for {$year} EFY.");

        $narrative = EdrPlanDocument::with('preparer')
            ->where('year', $year)
            ->latest('updated_at')
            ->latest('id')
            ->first();
        $departmentNames = Department::whereIn(
            'id',
            $plans->pluck('items')->flatten()->flatMap(fn ($item) => $item->performers ?? [])->unique()
        )->pluck('name', 'id');

        AuditLog::log('downloaded organization EDR plan Word document', $narrative, "Downloaded organization plan narration for {$year} EFY");

        return $exporter->download($plans, $narrative, $departmentNames, $year);
    }

    public function downloadNarrativeAttachment(Request $request)
    {
        $this->authorizeOrganizationNarrative();
        $data = $request->validate(['year' => 'required|integer|in:' . implode(',', \App\Services\CalendarHelper::getAppYears())]);
        $document = EdrPlanDocument::where('year', $data['year'])->firstOrFail();
        abort_unless($document->word_file_path && Storage::disk('local')->exists($document->word_file_path), 404);
        return Storage::disk('local')->download($document->word_file_path, $document->word_file_name, ['Cache-Control' => 'private, no-store']);
    }

    public function downloadObjective(StrategicObjective $objective)
    {
        abort_unless($objective->status === 'approved', 404);
        abort_unless(StrategicObjective::visibleToUser(auth()->user())->whereKey($objective->id)->exists(), 403);
        $objective->load(['department', 'leadDepartment', 'children.children.children']);
        return Pdf::loadView('edr_plans.objectives_pdf', ['objectives' => collect([$objective]), 'title' => "Strategic Objective {$objective->code}"])
            ->setPaper('a4', 'landscape')->download('Strategic_Objective_' . $this->safeName($objective->code) . '.pdf');
    }

    private function planPdf(Plan $plan, $items, string $filename, ?StrategicObjective $selected = null)
    {
        $departmentNames = Department::whereIn('id', $items->flatMap(fn ($item) => $item->performers ?? [])->unique())
            ->pluck('name', 'id');
        $narrative = EdrPlanDocument::with('preparer')->where('year', $plan->year)->first();
        return Pdf::loadView('edr_plans.plan_pdf', [
            'plan' => $plan, 'items' => $items->sortBy(fn ($item) => $item->strategicObjective?->code),
            'months' => self::MONTHS, 'selectedObjective' => $selected, 'departmentNames' => $departmentNames,
            'narrative' => $narrative,
        ])->setPaper('a4', 'landscape')->download($filename);
    }

    private function authorizeOrganizationNarrative(): void
    {
        $user = auth()->user();
        abort_unless($user->isPlanReportDepartmentExpert() || $user->isPlanReportDirectorate() || $user->isPlanReportHeadOffice() || $user->isSystemAdmin(), 403);
    }

    private function authorizeNarrativeEdit(EdrPlanDocument $document): void
    {
        $this->authorizeOrganizationNarrative();
        if (auth()->user()->isSystemAdmin()) return;
        $status = $document->exists ? $document->status : 'draft';
        abort_unless(
            (auth()->user()->isPlanReportDepartmentExpert() && $status === 'draft') ||
            (auth()->user()->isPlanReportDirectorate() && $status === 'submitted_to_plan_report_directorate') ||
            (auth()->user()->isPlanReportHeadOffice() && $status === 'submitted_to_plan_report_head'), 403
        );
    }

    private function authorizePlanView(Plan $plan): void
    {
        $user = auth()->user();
        abort_unless($user->hasPermission('view_plans'), 403);
        if (!$user->isSystemAdmin() && !$user->hasPermission('view_all_plans') && !$user->hasPermission('approve_plan')) {
            abort_unless((int) $plan->department_id === (int) $user->department_id, 403);
        }
    }

    private function safeName(string $value): string
    {
        return trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $value), '_');
    }
}
