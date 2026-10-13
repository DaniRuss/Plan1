<?php

namespace App\Http\Controllers;

use App\Models\TrainJourney;
use Illuminate\Http\Request;

class TrainJourneyController extends Controller
{
    public function index()
    {
        $journeys = TrainJourney::orderBy('departure_time', 'desc')->paginate(15);
        return view('trains.index', compact('journeys'));
    }

    public function create()
    {
        return view('trains.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'train_number' => 'required|string',
            'origin_station' => 'required|string',
            'destination_station' => 'required|string',
            'departure_time' => 'required|date'
        ]);

        TrainJourney::create($request->all());

        return redirect()->route('trains.index')->with('success', 'Train journey scheduled successfully!');
    }

    /**
     * Mark a train as departed (In Transit)
     */
    public function depart(TrainJourney $journey)
    {
        $journey->update([
            'status' => 'in_transit',
            'departure_time' => now()
        ]);

        // Update all linked cargo
        foreach ($journey->consignments as $cargo) {
            $cargo->logStatus('In Transit', $journey->origin_station, "Train $journey->train_number departed.");
        }

        return back()->with('success', "Train $journey->train_number has departed.");
    }

    /**
     * Mark a train as arrived (Stage 3)
     */
    public function arrive(TrainJourney $journey)
    {
        $journey->update([
            'status' => 'completed',
            'arrival_time' => now()
        ]);

        // Update all linked cargo
        foreach ($journey->consignments as $cargo) {
            $cargo->logStatus('Arrived Destination', $journey->destination_station, "Train $journey->train_number arrived at destination.");
        }

        return back()->with('success', "Train $journey->train_number has arrived at destination.");
    }
}
