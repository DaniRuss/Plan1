@extends('layouts.app')
@section('page_header_hidden', '1')
@section('title','Reporting Team')
@section('content')
<div class="container-fluid py-3"><h1 class="h3">Reporting team: {{ $department->name }}</h1>
<div class="alert alert-info">All department experts share draft data. Only the coordinator or backup can submit it. Saving this team activates shared reporting for new and draft periods. Reports already under review keep their existing workflow.</div>
<form method="POST" action="{{ route('reporting-teams.update',$department) }}" class="card card-body">@csrf @method('PUT')
<input type="hidden" name="version" value="{{ $team?->version ?? 0 }}">
@foreach(['coordinator_id'=>'Coordinator','backup_id'=>'Backup coordinator'] as $field=>$label)<div class="form-group"><label for="{{ $field }}">{{ $label }}</label><select id="{{ $field }}" name="{{ $field }}" required class="form-control"><option value="">Choose an active department expert</option>@foreach($experts as $expert)<option value="{{ $expert->id }}" @selected(old($field,$team?->{$field})==$expert->id)>{{ $expert->name }}</option>@endforeach</select></div>@endforeach
<label for="team-reason">Reason for assignment / replacement</label><textarea id="team-reason" name="reason" required minlength="5" maxlength="2000" class="form-control mb-3">{{ old('reason') }}</textarea>
<div><button class="btn btn-primary">Save reporting team</button><a class="btn btn-outline-secondary" href="{{ route('shared-reporting.index',['department_id'=>$department->id]) }}">Back</a></div></form></div>
@endsection
