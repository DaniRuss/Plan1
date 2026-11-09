<?php

namespace App\Services;

use App\Models\{AuditLog, StrategicObjective, User};

/** Field-level evidence stays in Details; list pages receive only attribution. */
class ObjectiveRevisionHistory
{
    private const ROLE_LABELS = [
        'system_admin'=>'System Administrator', 'plan_report_department_expert'=>'Plan and Report Expert',
        'plan_report_directorate'=>'Plan and Report Directorate', 'plan_report_head_office'=>'Plan and Report Chief (Head)',
        'department_expert'=>'Department Expert', 'department_directorate'=>'Department Directorate',
        'department_chief'=>'Department Chief (Head)', 'head_office'=>'Head Office',
    ];

    public function loadApprovedAttribution(iterable $objectives): void
    {
        $indexed = collect();
        $visit = function ($objectives) use (&$visit, $indexed): void {
            foreach ($objectives as $objective) {
                if ($indexed->has($objective->id)) continue;
                $indexed->put($objective->id, $objective);
                if ($objective->relationLoaded('children')) $visit($objective->children);
            }
        };
        $visit($objectives);
        if ($indexed->isEmpty()) return;

        // Filter legacy JSON in PHP, so old malformed/plain-text audit details do not
        // break either MySQL or SQLite JSON functions. One query for the visible tree.
        $latest = AuditLog::where('model_type', StrategicObjective::class)
            ->where('action','strategic_objective_updated')->whereIn('model_id',$indexed->keys())
            ->orderByDesc('created_at')->orderByDesc('id')->get(['id','model_id','user_id','details','created_at'])
            ->filter(function ($log) {
                $details = json_decode($log->details ?? '', true);
                return is_array($details) && ($details['stage'] ?? null) === 'approved'
                    && !in_array($details['action'] ?? null, ['reporting_frequency_change_requested','reporting_frequency_change_reject'], true)
                    && is_array($details['changes'] ?? null) && count($details['changes']) > 0;
            })->unique('model_id');
        $latest->loadMissing('user.roles');
        $byObjective = $latest->keyBy('model_id');
        foreach ($indexed as $id=>$objective) {
            $log = $byObjective->get($id);
            $objective->setRelation('approvedEditAttribution', $log ? $this->attribution($log) : null);
        }
    }

    public function attribution(AuditLog $log, ?array $details = null): array
    {
        $details ??= json_decode($log->details ?? '', true) ?: [];
        $editor = is_array($details['editor'] ?? null) ? $details['editor'] : [];
        $roleLabels = null;
        if (is_array($editor['roles'] ?? null)) {
            $roleLabels = collect($editor['roles'])->map(fn ($role) => is_array($role) ? ($role['label'] ?? $this->roleLabel($role['name'] ?? '')) : $this->roleLabel((string)$role))->filter()->join(', ');
        }
        if ($roleLabels === null) {
            $currentRoles = $log->user?->getRoleNames()->map(fn ($name) => $this->roleLabel($name))->join(', ');
            $roleLabels = $currentRoles ? 'Current role: '.$currentRoles : 'Role not recorded';
        }
        return [
            'editorName'=>$editor['name'] ?? $log->user?->name ?? 'Editor unavailable',
            'roleLabel'=>$roleLabels ?: 'No role assigned at edit time',
            'editedAt'=>$log->created_at?->copy()->timezone(config('app.timezone')),
        ];
    }

    public function record(StrategicObjective $objective, User $user, array $changes, ?string $reason, ?StrategicObjective $source = null): AuditLog
    {
        $details = [
            'revision'=>$objective->revision_number, 'stage'=>$objective->status,
            'editor'=>$this->editorSnapshot($user),
            'reason'=>$reason, 'changes'=>$changes,
        ];
        if ($source) $details['source_objective'] = ['id'=>$source->id,'code'=>$source->code];
        return AuditLog::log('strategic_objective_updated', $objective, $details);
    }

    public function editorSnapshot(User $user): array
    {
        return ['id'=>$user->id,'name'=>$user->name,'department_id'=>$user->department_id,
            'roles'=>$user->roles->sortBy('name')->map(fn ($role) => ['name'=>$role->name,'label'=>$this->roleLabel($role->name)])->values()->all()];
    }

    private function roleLabel(string $name): string
    {
        return self::ROLE_LABELS[$name] ?? ucwords(str_replace('_',' ',$name));
    }
}
