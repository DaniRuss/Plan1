<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PerformanceAttachment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PerformanceAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_evidence_is_private_and_department_scoped(): void
    {
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);

        $ownerDepartment = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $otherDepartment = Department::create(['name' => 'Finance', 'code' => 'FIN']);

        $owner = User::factory()->create(['department_id' => $ownerDepartment->id, 'is_active' => true]);
        $owner->roles()->sync([Role::where('name', 'department_expert')->value('id')]);

        $otherUser = User::factory()->create(['department_id' => $otherDepartment->id, 'is_active' => true]);
        $otherUser->roles()->sync([Role::where('name', 'department_expert')->value('id')]);

        $reviewer = User::factory()->create(['department_id' => $otherDepartment->id, 'is_active' => true]);
        $reviewer->roles()->sync([Role::where('name', 'plan_report_department_expert')->value('id')]);

        $path = 'performance_evidence/1/2026/weekly/july/week-1/evidence.pdf';
        Storage::disk('local')->put($path, 'supporting evidence');

        $attachment = PerformanceAttachment::create([
            'department_id' => $ownerDepartment->id,
            'year' => 2026,
            'month' => 'july',
            'week_number' => 1,
            'frequency' => 'weekly',
            'file_path' => $path,
            'original_name' => 'evidence.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 19,
            'uploaded_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('performance-attachments.download', $attachment))
            ->assertOk()
            ->assertDownload('evidence.pdf');

        $this->actingAs($otherUser)
            ->get(route('performance-attachments.download', $attachment))
            ->assertForbidden();

        $this->actingAs($reviewer)
            ->get(route('performance-attachments.download', $attachment))
            ->assertOk()
            ->assertDownload('evidence.pdf');
    }
}
