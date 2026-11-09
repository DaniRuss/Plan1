<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\HistoricalPerformanceImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HistoricalPerformanceImporter
{
    public ?array $repairSummary = null;

    public function __construct(private readonly HistoricalPerformanceWorkbook $workbook) {}

    public function import(UploadedFile $file, int $year, User $user, bool $repairOnly = false): HistoricalPerformanceImport
    {
        $this->repairSummary = null;
        $hash = hash_file('sha256', $file->getRealPath());
        $existing = HistoricalPerformanceImport::where('year',$year)->where('source_hash',$hash)->first();
        if ($repairOnly && !$existing) {
            throw ValidationException::withMessages(['workbook'=>'Repair requires the unchanged original workbook and the same source fiscal year. No matching checksum was found; no data was changed.']);
        }
        $rows = $this->workbook->parse($file->getRealPath());
        if ($rows->isEmpty()) throw ValidationException::withMessages(['workbook'=>'No historical KPI rows were found in this workbook.']);
        if ($existing) {
            return $this->restoreMissingValues($existing, $rows, $user);
        }

        return DB::transaction(function () use ($file,$year,$user,$hash,$rows) {
            $import = HistoricalPerformanceImport::create([
                'year'=>$year,'original_filename'=>$file->getClientOriginalName(),'source_hash'=>$hash,
                'status'=>'locked','imported_by'=>$user->id,
            ]);
            foreach ($rows as $row) $import->kpis()->create($row);
            AuditLog::log('historical_performance_imported',$import,[
                'year'=>$year,'filename'=>$file->getClientOriginalName(),'rows'=>$rows->count(),'source_hash'=>$hash,
            ]);
            return $import;
        });
    }

    private function restoreMissingValues(HistoricalPerformanceImport $import, Collection $source, User $user): HistoricalPerformanceImport
    {
        return DB::transaction(function () use ($import, $source, $user) {
            $sourceRows = $source->groupBy('source_row');
            $changes = [];
            $values = 0;
            $skipped = 0;
            foreach ($import->kpis()->lockForUpdate()->get() as $kpi) {
                $candidates = $sourceRows->get($kpi->source_row, collect());
                $row = $candidates->count() === 1 ? $candidates->first() : null;
                if (!$row || $kpi->code !== $row['code'] || $kpi->description !== $row['description']
                    || !$this->workbook->equivalentUnits($kpi->unit, $row['unit'])) {
                    $skipped++;
                    continue;
                }
                $updates = [];
                foreach (['weight', 'annual_target', 'annual_actual', 'reported_achievement'] as $field) {
                    if ($kpi->{$field} === null && $row[$field] !== null) $updates[$field] = $row[$field];
                }
                if ($updates === []) continue;
                $changes[] = ['historical_kpi_id'=>$kpi->id, 'source_row'=>$kpi->source_row, 'code'=>$kpi->code, 'restored'=>$updates];
                $values += count($updates);
                $kpi->fill($updates + ['version'=>$kpi->version + 1, 'updated_by'=>$user->id])->save();
            }
            $this->repairSummary = ['rows'=>count($changes), 'values'=>$values, 'skipped'=>$skipped];
            if ($changes !== []) {
                AuditLog::create([
                    'user_id'=>$user->id, 'action'=>'historical_import_values_repaired',
                    'model_type'=>HistoricalPerformanceImport::class, 'model_id'=>$import->id,
                    'details'=>json_encode(['source_hash'=>$import->source_hash, 'year'=>$import->year, 'changes'=>$changes, 'summary'=>$this->repairSummary]),
                    'ip_address'=>request()->ip(),
                ]);
            }
            return $import;
        });
    }
}
