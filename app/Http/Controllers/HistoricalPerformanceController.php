<?php

namespace App\Http\Controllers;

use App\Models\{AuditLog,HistoricalKpi,HistoricalKpiMapping,StrategicObjective};
use App\Services\{HistoricalMappingSuggester,HistoricalPerformanceImporter,HistoricalPerformanceWorkbook};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HistoricalPerformanceController extends Controller
{
    public function import(Request $request, HistoricalPerformanceImporter $importer, HistoricalMappingSuggester $suggester)
    {
        $this->manage($request);
        $data=$request->validate(['historical_year'=>'required|integer|min:1900|max:9999','workbook'=>'required|file|mimes:xlsx,xls|max:20480','repair_only'=>'nullable|in:1']);
        $import=$importer->import($request->file('workbook'),(int)$data['historical_year'],$request->user(),$request->boolean('repair_only'));
        if ($repair = $importer->repairSummary) {
            return back()->with('success', "Existing source verified: {$repair['values']} missing values restored in {$repair['rows']} activities. Existing values, KPI links and review states were not changed. {$repair['skipped']} changed or unverifiable rows skipped.");
        }
        $suggestions=$suggester->suggest($import,$request->user());
        return back()->with('success',"Historical workbook imported: {$import->kpis()->count()} rows, {$suggestions} draft mapping suggestions created.");
    }

    public function updateKpi(Request $request, HistoricalKpi $historicalKpi, HistoricalPerformanceWorkbook $workbook)
    {
        $this->manage($request);
        $data=$request->validate(['unit'=>'nullable|string|max:150','goal_direction'=>'required|in:increase,decrease','direction_status'=>'required|in:needs_review,confirmed']);
        $unit=filled($data['unit']??null)?trim($data['unit']):null;
        $historicalKpi->update(['unit'=>$unit,'normalized_unit'=>$workbook->normalizeUnit($unit),'unit_status'=>$unit?'specified':'missing','goal_direction'=>$data['goal_direction'],'direction_status'=>$data['direction_status'],'updated_by'=>$request->user()->id,'version'=>$historicalKpi->version+1]);
        AuditLog::log('historical_kpi_updated',$historicalKpi,$data);
        return back()->with('success','Historical KPI measurement details updated.');
    }

    public function saveMapping(Request $request, HistoricalKpi $historicalKpi)
    {
        $this->manage($request);
        $data=$request->validate(['mapping_id'=>'nullable|integer','current_objective_id'=>'nullable|exists:strategic_objectives,id','mapping_type'=>['required',Rule::in(HistoricalKpiMapping::TYPES)],'comparable'=>'nullable|boolean','confidence'=>'nullable|numeric|min:0|max:100','expert_comment'=>'nullable|string|max:4000']);
        if(!in_array($data['mapping_type'],['retired','not_comparable'],true) && empty($data['current_objective_id'])) return back()->withErrors(['current_objective_id'=>'Select a current objective for this mapping.']);
        $mapping=isset($data['mapping_id'])?$historicalKpi->mappings()->findOrFail($data['mapping_id']):new HistoricalKpiMapping(['historical_kpi_id'=>$historicalKpi->id]);
        $mapping->fill($data+['prepared_by'=>$request->user()->id]);$mapping->comparable=$request->boolean('comparable')&&!in_array($data['mapping_type'],['retired','not_comparable'],true);$mapping->status='draft';$mapping->reviewed_by=null;$mapping->reviewed_at=null;$mapping->version=($mapping->version?:0)+1;$mapping->save();
        AuditLog::log('historical_mapping_saved',$mapping,['historical_kpi_id'=>$historicalKpi->id,'current_objective_id'=>$mapping->current_objective_id,'type'=>$mapping->mapping_type]);
        return back()->with('success','Mapping saved as draft.');
    }

    public function submit(Request $request, HistoricalKpiMapping $mapping)
    {
        $this->manage($request);$mapping->update(['status'=>'submitted','prepared_by'=>$request->user()->id]);AuditLog::log('historical_mapping_submitted',$mapping);return back()->with('success','Mapping submitted for review.');
    }

    public function review(Request $request, HistoricalKpiMapping $mapping)
    {
        $this->reviewer($request);$data=$request->validate(['decision'=>'required|in:approved,rejected','review_comment'=>'nullable|required_if:decision,rejected|string|max:4000']);
        $mapping->update(['status'=>$data['decision'],'reviewed_by'=>$request->user()->id,'reviewed_at'=>now(),'review_comment'=>$data['review_comment']??null]);AuditLog::log('historical_mapping_reviewed',$mapping,$data);return back()->with('success','Mapping review recorded.');
    }

    private function manage(Request $request): void
    {
        $u=$request->user();abort_unless($u&&($u->isSystemAdmin()||$u->isPlanReportDepartmentExpert()||$u->isPlanReportDirectorate()||$u->isPlanReportHeadOffice()),403);
    }
    private function reviewer(Request $request): void
    {
        $u=$request->user();abort_unless($u&&($u->isSystemAdmin()||$u->isPlanReportDirectorate()||$u->isPlanReportHeadOffice()),403);
    }
}
