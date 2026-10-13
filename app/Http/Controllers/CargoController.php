<?php

namespace App\Http\Controllers;

use App\Models\CargoConsignment;
use App\Models\LogisticsCompany;
use App\Models\CargoMovementLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CargoController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total' => CargoConsignment::count(),
            'in_transit' => CargoConsignment::where('current_status', 'In Transit')->count(),
            'awaiting_transfer' => CargoConsignment::where('current_status', 'Arrived Destination')->count(),
            'dispatched' => CargoConsignment::where('current_status', 'Dispatched')->count(),
        ];
        
        $latestLogs = CargoMovementLog::with(['consignment', 'operator'])
            ->orderBy('recorded_at', 'desc')
            ->limit(10)
            ->get();
            
        return view('cargo.dashboard', compact('stats', 'latestLogs'));
    }

    public function index()
    {
        $consignments = CargoConsignment::with(['trainJourney', 'logisticsCompany'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
            
        return view('cargo.index', compact('consignments'));
    }

    public function create()
    {
        $companies = LogisticsCompany::orderBy('name')->get();
        return view('cargo.create', compact('companies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'consignment_number' => 'required|string|max:255',
            'shipper_name' => 'required|string|max:255',
            'origin_station' => 'required|string|max:255',
            'destination_station' => 'required|string|max:255',
            'goods_type' => 'nullable|string',
            'weight' => 'nullable|numeric',
            'container_id' => 'nullable|string',
        ]);

        // Generate a unique tracking ID
        $trackingId = 'EDR-' . strtoupper(Str::random(8));
        while (CargoConsignment::where('tracking_id', $trackingId)->exists()) {
            $trackingId = 'EDR-' . strtoupper(Str::random(8));
        }

        $cargo = CargoConsignment::create(array_merge(
            $request->all(),
            ['tracking_id' => $trackingId, 'current_status' => 'Arrived Origin']
        ));

        // Log the initial status
        $cargo->logStatus('Arrived Origin', $cargo->origin_station, 'Cargo registered at origin station.');

        return redirect()->route('cargo.index')->with('success', "Cargo registered successfully! Tracking ID: $trackingId");
    }

    public function show($id)
    {
        $cargo = CargoConsignment::with(['trainJourney', 'logisticsCompany', 'movementLogs.operator'])->findOrFail($id);
        
        // Fetch available trains from the origin station for assignment
        $availableTrains = \App\Models\TrainJourney::where('origin_station', $cargo->origin_station)
            ->where('status', 'scheduled')
            ->get();
            
        $companies = \App\Models\LogisticsCompany::orderBy('name')->get();
            
        return view('cargo.show', compact('cargo', 'availableTrains', 'companies'));
    }

    public function assignTrain(Request $request, $id)
    {
        $cargo = CargoConsignment::findOrFail($id);
        $journey = \App\Models\TrainJourney::findOrFail($request->train_journey_id);

        $cargo->update([
            'train_journey_id' => $journey->id,
            'current_status' => 'In Transit'
        ]);

        $cargo->logStatus('In Transit', $cargo->origin_station, "Linked to Train Journey: {$journey->train_number}.");

        return redirect()->route('cargo.show', $id)->with('success', "Cargo linked to Train {$journey->train_number} and marked as In Transit.");
    }

    public function transfer(Request $request, $id)
    {
        $cargo = CargoConsignment::findOrFail($id);
        $cargo->update([
            'logistics_company_id' => $request->logistics_company_id,
        ]);

        $company = \App\Models\LogisticsCompany::find($request->logistics_company_id);
        $cargo->logStatus('Transferred', $cargo->destination_station, "Handed over to {$company->name}. Personnel: {$request->personnel_name}");

        return redirect()->route('cargo.show', $id)->with('success', "Cargo transferred to {$company->name}.");
    }

    public function accept(Request $request, $id)
    {
        $cargo = CargoConsignment::findOrFail($id);
        $cargo->logStatus('Accepted', $cargo->destination_station, "Company formally accepted the goods. Condition: {$request->condition}");

        return redirect()->route('cargo.show', $id)->with('success', "Cargo acceptance recorded.");
    }

    public function dispatch(Request $request, $id)
    {
        $cargo = CargoConsignment::findOrFail($id);
        $cargo->logStatus('Dispatched', $request->location, "Dispatched to {$request->destination_country} via {$request->mode}. Carrier: {$request->carrier}");

        return redirect()->route('cargo.show', $id)->with('success', "Cargo dispatched to final destination.");
    }
}
