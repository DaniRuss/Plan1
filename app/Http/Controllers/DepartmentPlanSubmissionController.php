<?php

namespace App\Http\Controllers;

use App\Models\DepartmentPlanSubmission;
use App\Models\AuditLog;
use App\Services\CalendarHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class DepartmentPlanSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canReceiveDepartmentPlans() || ($user->isDepartmentChief() && $user->department_id), 403);
        $filters = $request->validate(['year' => 'nullable|integer|in:' . implode(',', CalendarHelper::getAppYears())]);
        $query = DepartmentPlanSubmission::with(['department', 'submitter'])->latest();
        if (!$user->canReceiveDepartmentPlans()) $query->where('department_id', $user->department_id);
        if (!empty($filters['year'])) $query->where('year', $filters['year']);
        return view('plans.submissions', ['submissions' => $query->paginate(20)->withQueryString(), 'years' => CalendarHelper::getAppYears()]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isDepartmentChief() && $user->department_id, 403);
        // Keep existing clients that submit a single file working.
        if (!$request->hasFile('files') && $request->hasFile('file')) {
            $request->files->set('files', [$request->file('file')]);
        }
        $data = $request->validate([
            'year' => 'required|integer|in:' . implode(',', CalendarHelper::getAppYears()),
            'title' => 'required|string|max:255', 'notes' => 'nullable|string|max:5000',
            'files' => 'required|array|min:1|max:20',
            'files.*' => 'required|file|mimes:doc,docx,xls,xlsx|extensions:doc,docx,xls,xlsx|max:10240',
        ]);
        $paths = [];
        try {
            DB::transaction(function () use ($request, $user, $data, &$paths) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store("department_plans/{$user->department_id}/{$data['year']}", 'local');
                    if ($path === false) throw new \RuntimeException('Unable to save the uploaded plan file.');
                    $paths[] = $path;
                    $submission = DepartmentPlanSubmission::create([
                        'department_id' => $user->department_id, 'submitted_by' => $user->id,
                        'year' => $data['year'], 'title' => $data['title'], 'notes' => $data['notes'] ?? null,
                        'file_path' => $path, 'file_name' => $file->getClientOriginalName(),
                    ]);
                    AuditLog::log('submitted department plan file', $submission, 'Department plan sent to Plan and Report');
                }
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
        $count = count($paths);
        return redirect()->route('plan-submissions.index')->with('success', $count . ' plan ' . ($count === 1 ? 'file' : 'files') . ' sent. All Plan and Report users can now download them.');
    }

    public function download(Request $request, DepartmentPlanSubmission $submission)
    {
        $user = $request->user();
        abort_unless($user->canReceiveDepartmentPlans() || ($user->isDepartmentChief()
            && (int) $user->department_id === (int) $submission->department_id), 403);
        abort_unless(Storage::disk('local')->exists($submission->file_path), 404);
        return Storage::disk('local')->download($submission->file_path, $submission->file_name, ['Cache-Control' => 'private, no-store']);
    }
}
