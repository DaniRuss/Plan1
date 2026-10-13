@extends('layouts.app')

@section('title', 'Register New Cargo')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 font-weight-bold">
                    <i class="fas fa-plus-circle mr-2 text-primary"></i> Register Arrival at Origin Station
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('cargo.store') }}" method="POST">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small text-uppercase">Consignment Number <span class="text-danger">*</span></label>
                            <input type="text" name="consignment_number" class="form-control" placeholder="e.g. CON-2024-001" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small text-uppercase">Shipper Name <span class="text-danger">*</span></label>
                            <input type="text" name="shipper_name" class="form-control" placeholder="e.g. Ethiopian Coffee Exporters" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small text-uppercase">Origin Station <span class="text-danger">*</span></label>
                            <select name="origin_station" class="form-control select2" required>
                                <option value="">-- Select Origin --</option>
                                <option value="Furi-Lebu">Furi-Lebu (Addis Ababa)</option>
                                <option value="Indode">Indode</option>
                                <option value="Adama">Adama</option>
                                <option value="Mojo">Mojo (Dry Port)</option>
                                <option value="Dire Dawa">Dire Dawa</option>
                                <option value="Dewele">Dewele</option>
                                <option value="Nagad">Nagad (Djibouti)</option>
                                <option value="DCT">DCT (Djibouti Cargo Terminal)</option>
                                <option value="Doraleh">Doraleh Multipurpose Port</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold small text-uppercase">Destination Station <span class="text-danger">*</span></label>
                            <select name="destination_station" class="form-control select2" required>
                                <option value="">-- Select Destination --</option>
                                <option value="DCT">DCT (Djibouti Cargo Terminal)</option>
                                <option value="Doraleh">Doraleh Multipurpose Port</option>
                                <option value="Nagad">Nagad (Djibouti)</option>
                                <option value="Mojo">Mojo (Dry Port)</option>
                                <option value="Indode">Indode</option>
                                <option value="Furi-Lebu">Furi-Lebu (Addis Ababa)</option>
                            </select>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small text-uppercase">Type of Goods</label>
                            <input type="text" name="goods_type" class="form-control" placeholder="e.g. Coffee, Fertilizer">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small text-uppercase">Weight (Tons)</label>
                            <input type="number" step="0.01" name="weight" class="form-control" placeholder="0.00">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="font-weight-bold small text-uppercase">Container ID</label>
                            <input type="text" name="container_id" class="form-control" placeholder="e.g. MSKU1234567">
                        </div>
                    </div>

                    <div class="alert alert-info border-0 shadow-sm mt-3" style="background: rgba(var(--primary-rgb), 0.05);">
                        <i class="fas fa-info-circle mr-2"></i>
                        Registering this cargo will automatically set its status to <strong>"Arrived at Origin Station"</strong> and generate a unique tracking ID.
                    </div>

                    <div class="text-right mt-4">
                        <a href="{{ route('cargo.index') }}" class="btn btn-light px-4 mr-2">Cancel</a>
                        <button type="submit" class="btn btn-primary px-5 font-weight-bold">
                            <i class="fas fa-check-circle mr-1"></i> Confirm Arrival
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
