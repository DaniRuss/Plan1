<?php

namespace App\Http\Controllers;

use App\Models\PerformanceAttachment;
use Illuminate\Support\Facades\Storage;

class PerformanceAttachmentController extends Controller
{
    public function download(PerformanceAttachment $attachment)
    {
        $user = auth()->user();
        abort_unless(
            $user->hasPermission('view_performance')
            && ($user->hasOrganizationWidePerformanceAccess()
                || (int) $user->department_id === (int) $attachment->department_id),
            403
        );
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }
}
