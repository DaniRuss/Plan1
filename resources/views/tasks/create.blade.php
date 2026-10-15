@extends('layouts.app')

@section('title', 'Assign Task')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">New Task Assignment</h3>
                    </div>
                    <form action="{{ route('tasks.store') }}" method="POST">
                        @csrf
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="plan_item_id">Link to Strategic Objective / Plan Item</label>
                                        <select name="plan_item_id" id="plan_item_id"
                                            class="form-control select2 @error('plan_item_id') is-invalid @enderror"
                                            required>
                                            <option value="">-- Select Objective --</option>
                                            @foreach($planItems as $item)
                                                <option value="{{ $item->id }}" {{ old('plan_item_id') == $item->id ? 'selected' : '' }}>
                                                    [{{ $item->plan->year }}] {{ $item->strategicObjective->title }} (Target:
                                                    {{ \App\Support\NumberFormatter::format($item->annual_target) }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('plan_item_id')
                                            <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="title">Task Title</label>
                                <input type="text" name="title" id="title"
                                    class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}"
                                    placeholder="e.g., Conduct 10 regional trainings" required>
                                @error('title')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="description">Task Description / Instructions</label>
                                <textarea name="description" id="description" class="form-control" rows="4"
                                    placeholder="Detailed instructions for the employee...">{{ old('description') }}</textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="assigned_to">Assign To Employee</label>
                                        <select name="assigned_to" id="assigned_to"
                                            class="form-control select2 @error('assigned_to') is-invalid @enderror"
                                            required>
                                            <option value="">-- Select Employee --</option>
                                            @foreach($employees as $employee)
                                                <option value="{{ $employee->id }}" {{ old('assigned_to') == $employee->id ? 'selected' : '' }}>
                                                    {{ $employee->employee_number }} - {{ $employee->first_name }}
                                                    {{ $employee->last_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('assigned_to')
                                            <span class="invalid-feedback">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="start_date">Start Date</label>
                                        <input type="date" name="start_date" id="start_date" class="form-control"
                                            value="{{ old('start_date') }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="due_date">Due Date</label>
                                        <input type="date" name="due_date" id="due_date" class="form-control"
                                            value="{{ old('due_date') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-right">
                            <a href="{{ route('tasks.index') }}" class="btn btn-default">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">Assign Task</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            $('.select2').select2({
                theme: 'bootstrap4'
            });
        });
    </script>
@endpush