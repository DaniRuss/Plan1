<?php

namespace App\Http\Controllers;

use App\Models\Import;
use App\Models\ImportDocument;
use App\Models\ImportPayment;
use App\Models\ImportOperation;
use App\Models\ImportLog;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    // ── Dashboard / Control Tower ─────────────────────────────────────────
    public function dashboard()
    {
        $stats = [];
        foreach (Import::STAGE_ORDER as $status => $num) {
            $stats[$status] = Import::where('status', $status)->count();
        }

        $kpi = [
            'total'           => Import::count(),
            'active'          => Import::whereNotIn('status', ['DISPATCHED'])->count(),
            'waiting_ecd'     => $stats['DOCUMENT_SUBMITTED'],
            'waiting_release' => $stats['ECD_VALIDATED'],
            'waiting_wagon'   => $stats['RELEASE_DOC_RECEIVED'],
            'ready_loading'   => $stats['OPERATION_APPROVED'],
            'dispatched'      => $stats['DISPATCHED'],
        ];

        $recentJobs = Import::with('assignedUser')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        // Alerts: imports that are stuck / missing something
        $alerts = Import::with('documents', 'payments')
            ->whereNotIn('status', ['DISPATCHED'])
            ->get()
            ->filter(fn($i) => count($i->missing_alerts) > 0)
            ->take(5);

        return view('imports.dashboard', compact('stats', 'kpi', 'recentJobs', 'alerts'));
    }

    // ── Import List ───────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = Import::with('assignedUser')->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('cargo_type')) {
            $query->where('cargo_type', $request->cargo_type);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('job_id', 'like', '%' . $request->search . '%')
                  ->orWhere('shipper_name', 'like', '%' . $request->search . '%')
                  ->orWhere('consignee_name', 'like', '%' . $request->search . '%');
            });
        }

        $imports = $query->paginate(15)->withQueryString();
        return view('imports.index', compact('imports'));
    }

    // ── Create Import ─────────────────────────────────────────────────────
    public function create()
    {
        return view('imports.create');
    }

    // ── Store Import (Stage 1) ─────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'cargo_type'       => 'required|in:CNTR,FZ,MACHINERY',
            'shipper_name'     => 'required|string|max:255',
            'consignee_name'   => 'nullable|string|max:255',
            'freight_forwarder'=> 'nullable|string|max:255',
            'description'      => 'nullable|string',
            'weight'           => 'nullable|numeric',
            'container_count'  => 'nullable|integer',
            'bl_number'        => 'nullable|string',
            'vessel_name'      => 'nullable|string',
            'port_of_origin'   => 'nullable|string',
        ]);

        $import = Import::create(array_merge($data, [
            'job_id'           => Import::generateJobId(),
            'status'           => Import::S_DOCUMENT_SUBMITTED,
            'assigned_user_id' => auth()->id(),
        ]));

        // Seed required documents as "missing" placeholders
        $requiredDocs = ['INVOICE', 'PACKING_LIST'];
        $requiredDocs[] = ($import->cargo_type === 'FZ') ? 'WAYBILL' : 'BL';
        foreach ($requiredDocs as $type) {
            $import->documents()->create(['document_type' => $type, 'status' => 'missing']);
        }

        // Seed required payments as "pending" placeholders
        $import->payments()->create(['payment_type' => 'port_fee', 'status' => 'pending']);
        if ($import->cargo_type === 'MACHINERY') {
            $import->payments()->create(['payment_type' => 'lashing_fee', 'status' => 'pending']);
            $import->payments()->create(['payment_type' => 'loading_fee', 'status' => 'pending']);
        }
        if ($import->cargo_type === 'FZ') {
            $import->payments()->create(['payment_type' => 'storage_fee', 'status' => 'pending']);
        }

        // Create operations record
        $import->operation()->create([
            'lashing_required' => ($import->cargo_type === 'MACHINERY'),
        ]);

        // Log it
        $import->logs()->create([
            'status'  => Import::S_DOCUMENT_SUBMITTED,
            'action'  => 'Import job created and documents submitted.',
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('imports.show', $import->id)
            ->with('success', "Import job {$import->job_id} created successfully!");
    }

    // ── Import Detail ─────────────────────────────────────────────────────
    public function show(Import $import)
    {
        $import->load(['documents', 'payments', 'operation', 'logs.user', 'assignedUser']);
        return view('imports.show', compact('import'));
    }

    // ── Stage 2: ECD Validation ───────────────────────────────────────────
    public function validateECD(Request $request, Import $import)
    {
        $request->validate([
            'approval_number' => 'required|string',
            'notes'           => 'nullable|string'
        ], [
            'approval_number.required' => 'ECD Approval Reference is required.'
        ]);

        // Record the customs approval document
        $doc = $import->documents()->firstOrCreate(
            ['document_type' => 'CUSTOMS_APPROVAL'],
            ['status' => 'missing']
        );
        $doc->update([
            'status'           => 'verified',
            'reference_number' => $request->approval_number,
            'uploaded_by'      => auth()->id(),
            'uploaded_at'      => now(),
        ]);

        $import->advanceTo(Import::S_ECD_VALIDATED, "ECD validated (Ref: {$request->approval_number})");

        // Auto-create release doc placeholder
        $releaseType = $import->cargo_type === 'FZ' ? 'DECLARATION_4' : 'DO';
        $import->documents()->firstOrCreate(['document_type' => $releaseType], ['status' => 'missing']);

        return back()->with('success', 'ECD validation recorded. Job advanced to Stage 2.');
    }

    // ── Stage 3: Release Document ─────────────────────────────────────────
    public function attachReleaseDoc(Request $request, Import $import)
    {
        $request->validate(['reference_number' => 'required|string', 'notes' => 'nullable|string']);

        $docType = $import->cargo_type === 'FZ' ? 'DECLARATION_4' : 'DO';
        $import->documents()->updateOrCreate(
            ['document_type' => $docType],
            [
                'status'           => 'uploaded',
                'reference_number' => $request->reference_number,
                'notes'            => $request->notes,
                'uploaded_by'      => auth()->id(),
                'uploaded_at'      => now(),
            ]
        );

        $docLabel = $import->cargo_type === 'FZ' ? 'Declaration No.4' : 'Delivery Order (D/O)';
        $import->advanceTo(Import::S_RELEASE_DOC, "$docLabel received: {$request->reference_number}");

        return back()->with('success', "$docLabel attached. Job advanced to Stage 3.");
    }

    // ── Stage 4: Wagon Assignment ─────────────────────────────────────────
    public function assignWagon(Request $request, Import $import)
    {
        // Enforce Port Fee Payment before allowing wagon assignment
        $portFee = $import->payments()->where('payment_type', 'port_fee')->first();
        if (!$request->boolean('port_fee_paid') && (!$portFee || $portFee->status !== 'paid')) {
            return back()->with('error', 'Cannot assign wagon: Port Fee must be paid first.');
        }

        $request->validate([
            'wagon_id'      => 'required|string',
            'yard_location' => 'required|string',
        ]);

        $import->operation()->updateOrCreate(
            ['import_id' => $import->id],
            [
                'wagon_id'        => $request->wagon_id,
                'yard_location'   => $request->yard_location,
                'requires_escort' => $request->boolean('requires_escort'),
                'storage_location'=> $request->storage_location,
            ]
        );

        // Mark port fee as paid if indicated
        if ($request->boolean('port_fee_paid')) {
            $import->payments()->where('payment_type', 'port_fee')
                ->update(['status' => 'paid', 'paid_at' => now(), 'amount' => $request->port_fee_amount]);
        }

        $import->advanceTo(Import::S_WAGON_ASSIGNED, "Wagon {$request->wagon_id} assigned at {$request->yard_location}.");
        return back()->with('success', 'Wagon assigned. Job advanced to Stage 4.');
    }

    // ── Stage 5: Transfer to Railway Yard ────────────────────────────────
    public function transferToYard(Request $request, Import $import)
    {
        $import->operation()->update(['transfer_date' => now()]);

        $fzNotes = '';
        if ($import->cargo_type === 'FZ' && $request->filled('get_pass')) {
            $import->operation()->update(['get_pass_obtained' => true]);
            $fzNotes = ' Get Pass obtained.';
        }

        $import->advanceTo(Import::S_AT_RAILWAY_YARD, 'Cargo transferred to railway yard.' . $fzNotes);
        return back()->with('success', 'Transfer recorded. Job at Railway Yard (Stage 5).');
    }

    // ── Stage 6: Transit Documents (T1) ──────────────────────────────────
    public function submitTransitDocs(Request $request, Import $import)
    {
        $request->validate([
            'dj_t1_number' => 'required|string',
            'et_t1_number' => 'required|string',
        ]);

        foreach (['DJ_T1' => $request->dj_t1_number, 'ET_T1' => $request->et_t1_number] as $type => $ref) {
            $import->documents()->updateOrCreate(
                ['document_type' => $type],
                ['status' => 'uploaded', 'reference_number' => $ref, 'uploaded_by' => auth()->id(), 'uploaded_at' => now()]
            );
        }

        if ($import->cargo_type === 'FZ' && $request->filled('fz_waybill_number')) {
            $import->documents()->updateOrCreate(
                ['document_type' => 'WAYBILL'],
                ['status' => 'uploaded', 'reference_number' => $request->fz_waybill_number, 'uploaded_by' => auth()->id(), 'uploaded_at' => now()]
            );
        }

        $import->advanceTo(Import::S_TRANSIT_DOC_READY, "DJ T1: {$request->dj_t1_number} | ET T1: {$request->et_t1_number}");
        return back()->with('success', 'T1 documents recorded. Job advanced to Stage 6.');
    }

    // ── Stage 7: Operation Department Approval ────────────────────────────
    public function approveOperation(Request $request, Import $import)
    {
        // Enforce Lashing for Machinery before allowing operation approval
        if ($import->cargo_type === 'MACHINERY') {
             if (!$request->boolean('lashing_completed') && (!$import->operation || !$import->operation->lashing_completed)) {
                 return back()->with('error', 'Cannot approve operation: Machinery requires Lashing to be completed.');
             }
             
             // If completed now, mark it
             if ($request->boolean('lashing_completed')) {
                 $import->operation()->updateOrCreate(
                     ['import_id' => $import->id],
                     ['lashing_completed' => true]
                 );
                 if ($request->filled('lashing_amount')) {
                     $import->payments()->updateOrCreate(
                         ['payment_type' => 'lashing_fee'],
                         ['status' => 'paid', 'paid_at' => now(), 'amount' => $request->lashing_amount, 'recorded_by' => auth()->id()]
                     );
                 }
             }
        }

        $import->operation()->updateOrCreate(
            ['import_id' => $import->id],
            [
                'operation_approved_at' => now(),
                'approved_by'           => auth()->id(),
            ]
        );

        $import->advanceTo(Import::S_OPERATION_APPROVED, $request->notes ?? 'Operation department approved for loading.');
        return back()->with('success', 'Operation approved. Job cleared for train loading (Stage 7).');
    }

    // ── Stage 8: Train Loading ────────────────────────────────────────────
    public function markLoaded(Request $request, Import $import)
    {
        $request->validate(['train_id' => 'required|string']);

        $import->operation()->updateOrCreate(
            ['import_id' => $import->id],
            [
                'train_id'     => $request->train_id,
                'loading_time' => now(),
            ]
        );

        $import->advanceTo(Import::S_LOADED_ON_TRAIN, "Loaded onto Train {$request->train_id}.");

        return back()->with('success', 'Cargo marked as loaded on train. Ready for final dispatch.');
    }

    // ── Stage 9: Final Dispatch ───────────────────────────────────────────
    public function dispatchTrain(Request $request, Import $import)
    {
        $import->advanceTo(Import::S_DISPATCHED, 'Cargo formally dispatched from Djibouti.');
        return back()->with('success', 'Cargo successfully dispatched! Workflow complete.');
    }

    // ── Document Upload ───────────────────────────────────────────────────
    public function uploadDocument(Request $request, Import $import)
    {
        $request->validate([
            'document_type'    => 'required|string',
            'reference_number' => 'nullable|string',
            'notes'            => 'nullable|string',
        ]);

        $import->documents()->updateOrCreate(
            ['document_type' => $request->document_type],
            [
                'status'           => 'uploaded',
                'reference_number' => $request->reference_number,
                'notes'            => $request->notes,
                'uploaded_by'      => auth()->id(),
                'uploaded_at'      => now(),
            ]
        );

        $docLabel = ImportDocument::TYPE_LABELS[$request->document_type] ?? $request->document_type;
        $import->logs()->create([
            'user_id' => auth()->id(),
            'status'  => $import->status,
            'action'  => "{$docLabel} uploaded",
            'notes'   => $request->reference_number ? "Ref: {$request->reference_number}" : null,
        ]);

        return back()->with('success', 'Document recorded successfully.');
    }

    // ── Mark Payment ──────────────────────────────────────────────────────
    public function markPayment(Request $request, Import $import)
    {
        $request->validate(['payment_type' => 'required|string', 'amount' => 'required|numeric']);

        $import->payments()->updateOrCreate(
            ['payment_type' => $request->payment_type],
            ['status' => 'paid', 'amount' => $request->amount, 'receipt_number' => $request->receipt_number, 'paid_at' => now(), 'recorded_by' => auth()->id()]
        );

        $payLabel = ImportPayment::TYPE_LABELS[$request->payment_type] ?? ucfirst(str_replace('_', ' ', $request->payment_type));
        $import->logs()->create([
            'user_id' => auth()->id(),
            'status'  => $import->status,
            'action'  => "{$payLabel} marked paid",
            'notes'   => $request->receipt_number ? "Receipt: {$request->receipt_number}" : "Amount: ETB {$request->amount}",
        ]);

        return back()->with('success', "{$payLabel} marked as paid.");
    }
}
