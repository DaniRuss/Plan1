@extends('layouts.app')

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Import Strategic Objectives</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('objectives.index') }}">Strategic Objectives</a></li>
                        <li class="breadcrumb-item active">Import</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Upload Excel File</h3>
                        </div>
                        <form action="{{ route('objectives.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="exampleInputFile">EDR action-plan workbook</label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file" name="file" class="custom-file-input" id="exampleInputFile"
                                                required accept=".xlsx, .xls">
                                            <label class="custom-file-label" for="exampleInputFile">Choose file</label>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">Upload the EDR action-plan Excel file. Nothing is saved until you review and confirm the preview.</small>
                                </div>
                                <div class="form-group"><label for="target_year">Plan year (EFY)</label><input id="target_year" name="target_year" type="number" class="form-control" value="{{ old('target_year', 2019) }}" required></div>
                                <div class="form-group">
                                    <a href="{{ route('objectives.download-template') }}" class="btn btn-info btn-sm">
                                        <i class="fas fa-download"></i> Download Template
                                    </a>
                                </div>

                                <div class="alert alert-info">
                                    <h5><i class="icon fas fa-info"></i> Format Requirements</h5>
                                    <ul>
                                        <li>Expected columns A–W: code, KPI, weight, unit, baseline, annual target, July–June and quarter targets, performer.</li>
                                        <li>Duplicate or missing codes and existing objectives must be resolved before saving.</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">Upload and preview</button>
                                <a href="{{ route('objectives.index') }}" class="btn btn-default float-right">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('js')
    <script>
        $(function () {
            bsCustomFileInput.init();
        });
    </script>
@endpush
