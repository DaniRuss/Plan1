<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class WorkflowNotificationService
{
    public function users(iterable $users, string $title, string $message, string $url, string $type = 'info', ?User $actor = null): void
    {
        $recipients = collect($users)->filter()->unique('id')->reject(fn (User $user) => $actor && $user->id === $actor->id);
        if ($recipients->isEmpty()) return;

        Notification::send($recipients, new WorkflowNotification([
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'type' => $type,
            'icon' => match ($type) {
                'success' => 'fas fa-check-circle',
                'warning' => 'fas fa-exclamation-triangle',
                'danger' => 'fas fa-times-circle',
                default => 'fas fa-paper-plane',
            },
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
        ]));
    }

    public function roles(array|string $roles, string $title, string $message, string $url, string $type = 'info', ?int $departmentId = null, ?User $actor = null): void
    {
        $roles = (array) $roles;
        $users = User::query()->active()
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roles))
            ->get();
        $this->users($users, $title, $message, $url, $type, $actor);
    }

    public function performanceTransition(string $action, int $departmentId, string $period, string $url, User $actor): void
    {
        $departmentName = Department::query()->whereKey($departmentId)->value('name')
            ?? "Department #{$departmentId}";
        $context = "{$departmentName} — {$period} performance";
        $map = [
            'submit_to_directorate' => [[User::ROLE_DEPARTMENT_DIRECTORATE], 'Performance received', "{$context} was submitted for Directorate review.", 'info'],
            'return_to_expert' => [[User::ROLE_DEPARTMENT_EXPERT], 'Performance returned', "{$context} was returned for correction.", 'warning'],
            'submit_to_chief' => [[User::ROLE_DEPARTMENT_CHIEF], 'Performance awaiting approval', "{$context} was reviewed and sent to the Department Chief.", 'info'],
            'return_to_directorate' => [[User::ROLE_DEPARTMENT_DIRECTORATE], 'Performance returned', "{$context} was returned to Directorate for correction.", 'warning'],
            'approve_by_chief' => [[User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT], 'Department performance received', "{$context} was approved and sent by the Department Chief.", 'success'],
            'return_to_department_chief' => [[User::ROLE_DEPARTMENT_CHIEF], 'Performance returned', "{$context} was returned by Plan & Report for correction.", 'warning'],
            'submit_to_plan_report_directorate' => [[User::ROLE_PLAN_REPORT_DIRECTORATE], 'Performance awaiting Directorate review', "{$context} was reviewed by the Plan and Report Expert.", 'info'],
            'mark_reviewed_no_correction' => [[User::ROLE_PLAN_REPORT_DIRECTORATE], 'Performance awaiting Directorate confirmation', "{$context} was reviewed with no correction required and sent by the Plan and Report Expert for Directorate confirmation.", 'info'],
            'submit_to_head_office' => [[User::ROLE_PLAN_REPORT_HEAD_OFFICE], 'Performance awaiting final approval', "{$context} was accepted by the Directorate and sent for final Head Office approval.", 'info'],
            'return_to_plan_report_department' => [[User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT], 'Performance returned by Directorate', "{$context} requires correction.", 'warning'],
            'return_to_plan_report_directorate' => [[User::ROLE_PLAN_REPORT_DIRECTORATE], 'Performance returned by Head', "{$context} requires Directorate correction.", 'warning'],
            'approve_by_head_office' => [[User::ROLE_DEPARTMENT_EXPERT, User::ROLE_DEPARTMENT_DIRECTORATE, User::ROLE_DEPARTMENT_CHIEF, User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT, User::ROLE_PLAN_REPORT_DIRECTORATE], 'Performance finally approved', "{$context} received final Head Office approval.", 'success'],
        ];
        if (!isset($map[$action])) return;
        [$roles, $title, $message, $type] = $map[$action];
        $organizationWideReviewRoles = [User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT, User::ROLE_PLAN_REPORT_DIRECTORATE, User::ROLE_PLAN_REPORT_HEAD_OFFICE];
        $targetDepartment = count($roles) === 1 && in_array($roles[0], $organizationWideReviewRoles, true) ? null : $departmentId;
        $this->roles($roles, $title, $message, $url, $type, $targetDepartment, $actor);
    }
}
