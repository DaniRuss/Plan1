<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentPlanSubmission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DepartmentPlanSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function user(Department $department, ?string $role = null): User
    {
        $user = User::factory()->create(['department_id' => $department->id]);
        if ($role) $user->roles()->attach(Role::firstOrCreate(['name' => $role]));
        return $user;
    }

    public function test_chiefs_send_files_and_every_plan_report_user_can_download_them(): void
    {
        Storage::fake('local');
        $cargo = Department::create(['name' => 'Cargo', 'code' => 'CG']);
        $report = Department::create(['name' => 'Plan and Report', 'code' => 'PR']);
        $chief = $this->user($cargo, User::ROLE_DEPARTMENT_CHIEF);
        $recipients = [];
        foreach ([User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT, User::ROLE_PLAN_REPORT_DIRECTORATE, User::ROLE_PLAN_REPORT_HEAD_OFFICE] as $role) {
            $recipients[] = $this->user($report, $role);
        }
        // A department member without a reviewer role must also have access.
        $recipients[] = $this->user($report);
        $this->actingAs($chief)->post(route('plan-submissions.store'), [
                'year' => 2026, 'title' => 'Cargo plan', 'department_id' => $report->id,
                'files' => [
                    UploadedFile::fake()->create('Cargo.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                    UploadedFile::fake()->create('Cargo.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])->assertSessionHasNoErrors()->assertRedirect(route('plan-submissions.index'));
        $this->assertDatabaseCount('department_plan_submissions', 2);
        $submission = DepartmentPlanSubmission::firstOrFail();
        $this->assertEquals($cargo->id, $submission->department_id);
        Storage::disk('local')->assertExists($submission->file_path);
        foreach ($recipients as $recipient) {
            $this->actingAs($recipient)->get(route('plan-submissions.index'))->assertOk()->assertSee('Cargo.xlsx')->assertSee('Cargo.docx');
            $this->get(route('plan-submissions.download', $submission))->assertOk()->assertDownload('Cargo.xlsx');
        }
        $this->actingAs($chief)->get(route('plan-submissions.download', $submission))->assertOk();
        $otherChief = $this->user($report, User::ROLE_DEPARTMENT_CHIEF);
        // Use another department that is not part of Plan and Report.
        $other = Department::create(['name' => 'Engineering', 'code' => 'EN']);
        $otherChief->update(['department_id' => $other->id]);
        $this->actingAs($otherChief)->get(route('plan-submissions.download', $submission))->assertForbidden();
        $this->get(route('plan-submissions.index'))->assertOk()->assertDontSee('Cargo.xlsx');
        $ordinary = $this->user($cargo);
        $this->actingAs($ordinary)->get(route('plan-submissions.index'))->assertForbidden();
        $this->post(route('plan-submissions.store'), [])->assertForbidden();
        $this->get(route('plan-submissions.download', $submission))->assertForbidden();
    }

    public function test_invalid_files_are_rejected_and_non_chief_reviewers_cannot_send(): void
    {
        Storage::fake('local');
        $department = Department::create(['name' => 'Cargo', 'code' => 'CG']);
        $chief = $this->user($department, User::ROLE_DEPARTMENT_CHIEF);
        foreach ([UploadedFile::fake()->create('Plan.pdf', 20, 'application/pdf'), UploadedFile::fake()->create('Plan.docx', 10241, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')] as $file) {
            $this->actingAs($chief)->post(route('plan-submissions.store'), ['year' => 2026, 'title' => 'Plan', 'files' => [
                UploadedFile::fake()->create('Valid.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                $file,
            ]])->assertSessionHasErrors('files.1');
        }
        $this->assertDatabaseCount('department_plan_submissions', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $reviewer = $this->user($department, User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT);
        $this->actingAs($reviewer)->post(route('plan-submissions.store'), [])->assertForbidden();
    }

    public function test_failed_batch_rolls_back_records_and_removes_stored_files(): void
    {
        Storage::fake('local');
        $department = Department::create(['name' => 'Cargo', 'code' => 'CG']);
        $chief = $this->user($department, User::ROLE_DEPARTMENT_CHIEF);
        DepartmentPlanSubmission::creating(function ($submission) {
            if ($submission->file_name === 'Second.docx') {
                throw new \RuntimeException('Simulated database failure');
            }
        });
        try {
            $this->actingAs($chief)->post(route('plan-submissions.store'), [
                'year' => 2026, 'title' => 'Plan', 'files' => [
                    UploadedFile::fake()->create('First.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                    UploadedFile::fake()->create('Second.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])->assertStatus(500);
            $this->assertDatabaseCount('department_plan_submissions', 0);
            $this->assertDatabaseCount('audit_logs', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        } finally {
            DepartmentPlanSubmission::flushEventListeners();
        }
    }
}
