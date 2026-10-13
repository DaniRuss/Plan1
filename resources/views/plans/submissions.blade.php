@extends('layouts.app')
@section('title', 'Department Plan Files')
@section('content')
<div class="container-fluid py-3">
    <h1 class="h3">Department Plan Files</h1>
    <p class="text-muted">Department chiefs send their plans here. Every Plan and Report user can download submitted files.</p>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    @if(auth()->user()->isDepartmentChief() && auth()->user()->department_id)
    <form method="POST" action="{{ route('plan-submissions.store') }}" enctype="multipart/form-data" class="card card-body mb-3">
        @csrf
        <h2 class="h5">Send plan to Plan and Report</h2>
        <div class="form-group"><label for="plan-title">Plan title</label><input id="plan-title" name="title" value="{{ old('title') }}" maxlength="255" required class="form-control"></div>
        <div class="form-group"><label for="plan-year">Plan year</label><select id="plan-year" name="year" required class="form-control">@foreach($years as $year)<option value="{{ $year }}" @selected(old('year') == $year)>{{ $year }} EFY</option>@endforeach</select></div>
        <div class="form-group"><label for="plan-file">Excel or Word files</label><input id="plan-file" type="file" name="files[]" accept=".xls,.xlsx,.doc,.docx" multiple required class="form-control-file"><small>Select up to 20 files together, including both Word and Excel. Maximum 10 MB per file. Each file can be downloaded separately.</small></div>
        <div class="form-group"><label for="plan-notes">Notes</label><textarea id="plan-notes" name="notes" maxlength="5000" class="form-control">{{ old('notes') }}</textarea></div>
        <div><button class="btn btn-primary">Send Plan Files</button></div>
    </form>
    @endif
    <form method="GET" class="form-inline mb-3"><label for="filter-year" class="mr-2">Year</label><select id="filter-year" name="year" class="form-control mr-2"><option value="">All years</option>@foreach($years as $year)<option value="{{ $year }}" @selected(request('year') == $year)>{{ $year }} EFY</option>@endforeach</select><button class="btn btn-outline-primary">Filter</button></form>
    <div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Department</th><th>Plan</th><th>Year</th><th>Sent by</th><th>Sent at</th><th>File</th></tr></thead><tbody>
    @forelse($submissions as $submission)
        <tr><td>{{ $submission->department->name }}</td><td>{{ $submission->title }}@if($submission->notes)<small class="d-block text-muted">{{ $submission->notes }}</small>@endif</td><td>{{ $submission->year }} EFY</td><td>{{ $submission->submitter->name }}</td><td>{{ $submission->created_at->timezone('Africa/Nairobi')->format('d M Y H:i') }}</td><td><a href="{{ route('plan-submissions.download', $submission) }}" class="btn btn-sm btn-outline-primary">Download {{ $submission->file_name }}</a></td></tr>
    @empty<tr><td colspan="6" class="text-center text-muted">No plans have been sent yet.</td></tr>@endforelse
    </tbody></table></div></div>
    <div class="mt-3">{{ $submissions->links() }}</div>
</div>
@endsection
