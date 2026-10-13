<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StrategicObjective;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ObjectiveReportingFrequencyController extends Controller
{
    public function requestChange(Request $request, StrategicObjective $objective)
    {
        abort_unless($request->user()->canManageObjectiveFrequency() && $objective->status === 'approved', 403);
        $data = $request->validate([
            'reporting_frequency' => 'required|in:inherit,daily,weekly,monthly',
            'change_reason' => 'required|string|min:5|max:2000',
        ]);
        DB::transaction(function () use ($request, $objective, $data) {
            $objective = StrategicObjective::whereKey($objective->id)->lockForUpdate()->firstOrFail();
            abort_unless($objective->status === 'approved' && !$objective->pending_reporting_frequency, 409, 'A frequency change is already awaiting approval.');
            abort_if(($objective->reporting_frequency ?? 'inherit') === $data['reporting_frequency'], 422, 'Choose a different reporting frequency.');
            $objective->update([
                'pending_reporting_frequency' => $data['reporting_frequency'],
                'frequency_change_reason' => $data['change_reason'], 'frequency_requested_by' => $request->user()->id,
            ]);
            $this->audit($objective, $request, 'reporting_frequency_change_requested', $objective->reporting_frequency, $data['reporting_frequency']);
        });
        return back()->with('success', 'Frequency change sent for Head Office approval. The current frequency remains in use until approved.');
    }

    public function decide(Request $request, StrategicObjective $objective)
    {
        abort_unless($request->user()->isPlanReportHeadOffice() || $request->user()->isSystemAdmin(), 403);
        $data = $request->validate(['decision' => 'required|in:approve,reject']);
        DB::transaction(function () use ($objective, $request, $data) {
            $objective = StrategicObjective::whereKey($objective->id)->lockForUpdate()->firstOrFail();
            abort_unless($objective->status === 'approved' && $objective->pending_reporting_frequency, 409);
            $old = $objective->reporting_frequency;
            $new = $objective->pending_reporting_frequency === 'inherit' ? null : $objective->pending_reporting_frequency;
            $this->audit($objective, $request, 'reporting_frequency_change_' . $data['decision'], $old, $new);
            $objective->update([
                'reporting_frequency' => $data['decision'] === 'approve' ? $new : $old,
                'pending_reporting_frequency' => null, 'frequency_change_reason' => null, 'frequency_requested_by' => null,
                'revision_number' => (int) $objective->revision_number + 1,
            ]);
        });
        return back()->with('success', 'Frequency change ' . ($data['decision'] === 'approve' ? 'approved.' : 'rejected.'));
    }

    private function audit(StrategicObjective $objective, Request $request, string $action, ?string $old, ?string $new): void
    {
        AuditLog::log('strategic_objective_updated', $objective, [
            'action' => $action, 'reason' => $objective->frequency_change_reason,
            'stage' => $objective->status, 'revision' => $objective->revision_number,
            'editor' => ['id' => $request->user()->id, 'name' => $request->user()->name, 'department_id' => $request->user()->department_id],
            'requested_by' => $objective->frequency_requested_by,
            'changes' => ['reporting_frequency' => ['before' => $old ?? 'inherit', 'after' => $new ?? 'inherit']],
        ]);
    }
}
