@extends('layouts.app')
@section('page_header_hidden', '1')
@section('title','Shared Performance Report')
@section('content')
<div class="container-fluid py-3">
<a href="{{ route('shared-reporting.index',['department_id'=>$submission->department_id]) }}">Back to shared reporting</a>
<h1 class="h3 mt-3">{{ $submission->department->name }} · {{ ucfirst($submission->frequency) }} report</h1>
<p>{{ ucfirst($submission->month) }} {{ $submission->year }} @if($submission->week) · Week {{ $submission->week }} @endif · <strong>{{ ucwords(str_replace('_',' ',$submission->status)) }}</strong></p>
<div class="alert alert-info">Coordinator: {{ $team?->coordinator?->name ?? 'Not configured' }} · Backup: {{ $team?->backup?->name ?? 'Not configured' }}.<br>Use Save all activities to save the values and Completion state for every editable activity on this page. Blank values are not zeros. A stale save is rejected so another expert’s work cannot be overwritten. Submitted sections are locked.</div>
@include('performance._attachments',['attachments'=>$legacyAttachments])
@if($submission->workflow_note)<div class="alert alert-warning"><strong>Review note:</strong> {{ $submission->workflow_note }}</div>@endif
@if($entries->contains(fn ($entry) => $reporting->editable(auth()->user(),$entry)))
<form id="save-all-activities" method="POST" action="{{ route('shared-reporting.save-all',$submission) }}" enctype="multipart/form-data">@csrf @method('PUT')</form>
@endif
<div class="card card-outline card-primary shared-report-table">
<div class="card-header"><h2 class="card-title">{{ ucfirst($submission->month) }} {{ $submission->week ? 'W'.$submission->week : '' }} Performance</h2><span class="float-right small text-muted">Plan values are read-only</span></div>
<div class="table-responsive"><table class="table table-bordered table-sm mb-0" aria-label="Shared reporting activities">
<thead class="thead-light"><tr><th scope="col">S.No</th><th scope="col">ACTIVITIES</th><th scope="col">UNIT</th><th scope="col">MONTH PLAN</th>
@if($submission->frequency!=='monthly')<th scope="col" class="bg-info text-white">{{ strtoupper(substr($submission->month,0,3)) }} W{{ $submission->week }} Plan</th>@endif
<th scope="col" class="bg-primary text-white">{{ $submission->frequency==='monthly' ? 'Month Perf.' : 'W'.$submission->week.' Perf.' }}</th><th scope="col">%</th><th scope="col">Completion state</th></tr></thead><tbody>
@php
 $shownParents=[];
@endphp
@foreach($entries as $entry)
@php
 $item=$entry->planItem; $objective=$item->strategicObjective;
 $ancestors=collect(); $parent=$objective->parent; $visited=[$objective->id];
 while($parent && !in_array($parent->id,$visited,true)) { $ancestors->prepend($parent); $visited[]=$parent->id; $parent=$parent->parent; }
 $value=array_replace($values[$entry->id], old('entries.'.$entry->id, []));
 $editable=$reporting->editable(auth()->user(),$entry);
 $monthlyPlan=$item->{$submission->month.'_target'};
 $weeklyPlan=$values[$entry->id]['planned'] ?? $objective->weeklyPlanFromMonthlyTarget((float)$monthlyPlan,count(\App\Services\CalendarHelper::getMonthWeeks($submission->month,$submission->year)));
 $target=$submission->frequency==='monthly' ? $monthlyPlan : $weeklyPlan;
 $actual=$values[$entry->id]['actual'];
 $percentage=$actual!==null && $target!==null ? $objective->calculateWeeklyAchievement((float)$actual,(float)$target) : null;
@endphp
@foreach($ancestors as $ancestor)
@if(!isset($shownParents[$ancestor->id]))
<tr class="bg-light text-primary"><td class="text-center font-weight-bold">{{ $ancestor->code }}</td><th colspan="{{ $submission->frequency==='monthly' ? 6 : 7 }}" scope="rowgroup">{{ $ancestor->description }}</th></tr>
@php
 $shownParents[$ancestor->id]=true;
@endphp
@endif
@endforeach
<tr class="shared-activity-row">
<td class="text-center align-middle font-weight-bold">{{ $objective->code }}</td>
<td class="align-middle"><strong>{{ $objective->description }}</strong><small class="d-block text-muted mt-1">Reviewer: {{ $entry->section?->reviewer?->name ?? 'Not assigned' }}<br>Section: {{ ucwords(str_replace('_',' ',$entry->section?->status ?? 'unassigned')) }} | Last editor: {{ $entry->editor?->name ?? 'Not yet edited' }}</small></td>
<td class="align-middle small">{{ $item->unit ?: $objective->unit }}</td>
<td class="text-right align-middle text-muted">{{ $monthlyPlan===null ? 'N/A' : \App\Support\NumberFormatter::format($monthlyPlan,3) }}</td>
@if($submission->frequency!=='monthly')<td class="text-right align-middle font-weight-bold text-info bg-light">{{ \App\Support\NumberFormatter::format($weeklyPlan,3) }}</td>@endif
<td class="shared-actual-cell align-middle"><input @disabled(!$editable) type="hidden" form="save-all-activities" name="entries[{{ $entry->id }}][version]" value="{{ $entry->version }}"><fieldset @disabled(!$editable)><div class="row m-0">
@if($submission->frequency==='daily')
 @foreach($days as $day)<div class="col-12 form-group mb-1"><label for="day-{{ $entry->id }}-{{ $day }}">{{ $dayLabels[$day]['day_name'] }} {{ $dayLabels[$day]['day_num'] }}<small class="d-block text-muted">{{ $dayLabels[$day]['date'] }}</small></label><input class="form-control" type="number" step="any" form="save-all-activities" name="entries[{{ $entry->id }}][days][{{ $day }}]" id="day-{{ $entry->id }}-{{ $day }}" data-original-number="{{ $value['days'][$day] ?? '' }}" value="{{ \App\Support\NumberFormatter::format($value['days'][$day] ?? '',3,'.','') }}"></div>@endforeach
@elseif($submission->frequency==='weekly' && in_array($item->strategicObjective->template_type,['machinery','truck_haulage'],true))
 @foreach($item->strategicObjective->template_type==='machinery' ? ['qty'=>'Quantity','time'=>'Hours','days'=>'Days'] : ['count'=>'Truck count','cycle'=>'Cycle','tone'=>'Tonnage'] as $key=>$label)<div class="col-12 form-group mb-1"><label for="meta-{{ $entry->id }}-{{ $key }}">{{ $label }}</label><input class="form-control" type="number" min="0" step="any" form="save-all-activities" name="entries[{{ $entry->id }}][metadata][{{ $key }}]" id="meta-{{ $entry->id }}-{{ $key }}" data-original-number="{{ $value['metadata'][$key] ?? '' }}" value="{{ \App\Support\NumberFormatter::format($value['metadata'][$key] ?? '',3,'.','') }}"></div>@endforeach
@else
 <div class="col-12 form-group mb-1"><label for="actual-{{ $entry->id }}">Actual {{ $item->strategicObjective->isTemporal() ? '(hours:minutes or decimal hours)' : '' }}</label><input class="form-control" form="save-all-activities" name="entries[{{ $entry->id }}][actual]" id="actual-{{ $entry->id }}" data-original-number="{{ $value['actual'] ?? '' }}" value="{{ \App\Support\NumberFormatter::format($value['actual'] ?? '',3,'.','') }}" {{ $item->strategicObjective->isTemporal() ? '' : 'type=number step=any' }}></div>
@endif
</div></fieldset></td>
<td class="text-center align-middle">{{ $percentage===null ? '-' : \App\Support\NumberFormatter::format($percentage,2).'%' }}</td>
<td class="align-middle"><span class="badge badge-{{ in_array($entry->state,['ready','not_applicable'])?'success':'secondary' }} mb-2">{{ ucwords(str_replace('_',' ',$entry->state)) }}</span><fieldset @disabled(!$editable)><div class="mb-0"><label for="state-{{ $entry->id }}">Completion state</label><select id="state-{{ $entry->id }}" form="save-all-activities" name="entries[{{ $entry->id }}][state]" class="form-control">@foreach(['draft'=>'Draft','ready'=>'Ready for review','not_applicable'=>'Not applicable'] as $key=>$label)<option value="{{ $key }}" @selected(old('entries.'.$entry->id.'.state',$entry->state)===$key)>{{ $label }}</option>@endforeach</select></div>
</fieldset></td></tr>
<tr class="bg-light"><td></td><td colspan="{{ $submission->frequency==='monthly' ? 6 : 7 }}"><details class="py-1"><summary class="text-primary">Notes, evidence and review details @if($entry->evidence_required) | Evidence required @endif</summary>
@if($entry->section?->note)<p class="text-danger mt-2"><strong>Section review:</strong> {{ $entry->section->note }}</p>@endif
<fieldset @disabled(!$editable)><div class="row mt-2">@foreach($value['stations'] as $station=>$stationActual)<div class="col-md-3 form-group"><label>{{ $station }} station</label><input type="number" step="any" form="save-all-activities" name="entries[{{ $entry->id }}][stations][{{ $station }}]" data-original-number="{{ $stationActual }}" value="{{ \App\Support\NumberFormatter::format($stationActual,3,'.','') }}" class="form-control" aria-label="{{ $station }} station actual"></div>@endforeach
<div class="col-md-6 form-group"><label for="notes-{{ $entry->id }}">Notes</label><textarea id="notes-{{ $entry->id }}" form="save-all-activities" name="entries[{{ $entry->id }}][notes]" class="form-control" maxlength="5000">{{ $value['notes'] }}</textarea></div>
<div class="col-md-6 form-group"><label for="reason-{{ $entry->id }}">Reason for correction / not applicable</label><textarea id="reason-{{ $entry->id }}" form="save-all-activities" name="entries[{{ $entry->id }}][reason]" class="form-control" maxlength="2000">{{ old('entries.'.$entry->id.'.reason',$entry->reason) }}</textarea><small class="form-text text-muted">For a correction, enter at least 5 characters. Unchanged activities do not need a new reason.</small>@error('entries.'.$entry->id.'.reason')<div class="text-danger">{{ $message }}</div>@enderror</div>
<div class="col-md-6 form-group"><label for="evidence-{{ $entry->id }}">Evidence (up to 10 files, 10 MB each)</label><input id="evidence-{{ $entry->id }}" type="file" form="save-all-activities" name="entries[{{ $entry->id }}][files][]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png" class="form-control-file"></div>

</div></fieldset>@if($entry->state==='not_applicable')<p class="mt-2"><strong>Not applicable:</strong> {{ $entry->reason }}</p>@endif
@foreach($entry->evidence_paths ?? [] as $index=>$file)<a class="d-inline-block mr-3 mt-2" href="{{ route('shared-reporting.evidence',[$entry,$index]) }}">{{ $file['name'] }}</a>@endforeach
@if($reporting->canManageTeam(auth()->user(),$submission->department_id) && in_array($submission->status,['draft','submitted_to_directorate','submitted_to_chief','returned_to_department_chief'],true))
<details class="mt-3"><summary>Assign / replace reviewer and evidence requirement</summary><form method="POST" action="{{ route('shared-reporting.assign',$entry) }}" class="mt-2">@csrf @method('PUT')<input type="hidden" name="version" value="{{ $entry->version }}"><label for="reviewer-{{ $entry->id }}">Assigned directorate</label><select id="reviewer-{{ $entry->id }}" name="reviewer_id" required class="form-control mb-2"><option value="">Choose an active department directorate</option>@foreach($reviewers as $reviewer)<option value="{{ $reviewer->id }}" @selected($entry->section?->reviewer_id===$reviewer->id)>{{ $reviewer->name }}</option>@endforeach</select><label><input type="checkbox" name="evidence_required" value="1" @checked($entry->evidence_required)> Evidence required</label><textarea name="reason" required minlength="5" maxlength="2000" class="form-control mb-2" aria-label="Assignment change reason" placeholder="Reason for this assignment / change"></textarea><button class="btn btn-outline-primary btn-sm">Save reviewer assignment</button></form></details>@endif
</details></td></tr>
@endforeach
</tbody></table></div></div>
@if($entries->contains(fn ($entry) => $reporting->editable(auth()->user(),$entry)))
<div class="mb-4"><button type="submit" form="save-all-activities" class="btn btn-primary">Save all activities</button><small class="d-block text-muted mt-2">Saves all editable activities together. Save before submitting the report for review.</small></div>
@endif
<section class="card card-body"><h2 class="h5">Review sections</h2>
@foreach($submission->sections as $section)<div class="border rounded p-3 mb-2"><strong>{{ $section->reviewer->name }}</strong> · {{ ucwords(str_replace('_',' ',$section->status)) }} · {{ $section->entries->count() }} activities
@if($section->note)<p class="mb-1 text-muted">{{ $section->note }}</p>@endif
@if($submission->status==='submitted_to_directorate' && $section->status==='submitted_to_directorate' && (auth()->user()->isSystemAdmin() || $section->reviewer_id===auth()->id()))
<form method="POST" action="{{ route('shared-reporting.transition',$submission) }}" class="mt-2">@csrf<input type="hidden" name="version" value="{{ $section->version }}"><input type="hidden" name="section_id" value="{{ $section->id }}"><textarea name="note" class="form-control mb-2" maxlength="2000" aria-label="Section review note" placeholder="Review comment (required for a return)"></textarea><button name="action" value="review_section" class="btn btn-success btn-sm">Approve section</button><button name="action" value="return_section" class="btn btn-outline-danger btn-sm">Return this section to experts</button></form>@endif</div>@endforeach
@if($reporting->coordinator(auth()->user(),$submission) && in_array($submission->status,['draft','submitted_to_directorate'],true))<form method="POST" action="{{ route('shared-reporting.transition',$submission) }}" class="mt-2">@csrf<input type="hidden" name="version" value="{{ $submission->version }}"><input type="hidden" name="action" value="submit"><button class="btn btn-primary">Submit completed draft / returned sections</button></form>@endif
@include('shared_reporting.approval-actions')
</section>
<section class="card card-body"><h2 class="h5">Recent report history</h2>@forelse($history as $event)<details class="border-bottom py-2"><summary>{{ $event->action }} · {{ $event->user?->name ?? 'System' }} · {{ $event->created_at->timezone('Africa/Nairobi')->format('d M Y H:i') }}</summary><pre class="small p-2 bg-light" style="white-space:pre-wrap">{{ json_encode(json_decode($event->details ?? '',true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>@empty<p class="text-muted">No changes yet.</p>@endforelse</section>
</div>
@endsection

@push('styles')
<style>
.shared-report-table table {min-width:1050px;font-size:.875rem;}
.shared-report-table th {vertical-align:middle;text-align:center;white-space:nowrap;}
.shared-report-table td {padding:.5rem;}
.shared-report-table .shared-actual-cell {min-width:180px;max-width:300px;}
.shared-report-table .shared-actual-cell label {font-size:.75rem;color:#6c757d;}
.shared-report-table .shared-actual-cell .form-group {padding:0;}
.shared-report-table .shared-actual-cell input {text-align:right;}
.shared-report-table details summary {cursor:pointer;}
.shared-report-table fieldset {min-width:0;}
</style>
@endpush
