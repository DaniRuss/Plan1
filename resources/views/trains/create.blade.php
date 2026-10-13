@extends('layouts.app')

@section('title', 'Schedule Train Journey')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-calendar-plus mr-2 text-primary"></i> Schedule Train Journey
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('trains.store') }}" method="POST">
                    @csrf
                    
                    <div class="form-group">
                        <label class="font-weight-bold small text-uppercase">Train Number / ID <span class="text-danger">*</span></label>
                        <input type="text" name="train_number" class="form-control" placeholder="e.g. TR-2024-X1" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small text-uppercase">Origin <span class="text-danger">*</span></label>
                            <select name="origin_station" class="form-control" required>
                                <option value="">-- Select Origin --</option>
                                <option value="Furi-Lebu">Furi-Lebu (Addis Ababa)</option>
                                <option value="Indode">Indode</option>
                                <option value="Adama">Adama</option>
                                <option value="Mojo">Mojo (Dry Port)</option>
                                <option value="Dire Dawa">Dire Dawa</option>
                                <option value="Nagad">Nagad (Djibouti)</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small text-uppercase">Destination <span class="text-danger">*</span></label>
                            <select name="destination_station" class="form-control" required>
                                <option value="">-- Select Destination --</option>
                                <option value="DCT">DCT (Djibouti Port)</option>
                                <option value="Doraleh">Doraleh Port</option>
                                <option value="Nagad">Nagad (Djibouti)</option>
                                <option value="Mojo">Mojo (Dry Port)</option>
                                <option value="Furi-Lebu">Furi-Lebu (Addis Ababa)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small text-uppercase">Scheduled Departure <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="departure_time" class="form-control" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small text-uppercase">Locomotive #</label>
                            <input type="text" name="locomotive_number" class="form-control" placeholder="e.g. L-1004">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small text-uppercase">Driver Name</label>
                            <input type="text" name="driver_name" class="form-control" placeholder="e.g. Samuel J.">
                        </div>
                    </div>

                    <div class="text-right mt-4">
                        <a href="{{ route('trains.index') }}" class="btn btn-light px-4 mr-2">Cancel</a>
                        <button type="submit" class="btn btn-primary px-5 font-weight-bold">
                            <i class="fas fa-check-circle mr-1"></i> Save Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
