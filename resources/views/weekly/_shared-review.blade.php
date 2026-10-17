@if($sharedSubmission ?? null)
<section class="card card-body mb-3">
<strong>Department report review</strong>
<a href="{{ route('shared-reporting.show',$sharedSubmission) }}">Open Shared Reporting for activity details, evidence and correction history</a>
@if(auth()->user()->isPlanReportDepartmentExpert() || auth()->user()->isPlanReportDirectorate() || auth()->user()->isPlanReportHeadOffice() || auth()->user()->isSystemAdmin())
@include('shared_reporting.approval-actions',['submission'=>$sharedSubmission,'reporting'=>app(\App\Services\SharedReporting::class)])
@endif
</section>
@endif
