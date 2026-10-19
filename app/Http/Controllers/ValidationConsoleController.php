<?php

namespace App\Http\Controllers;

use App\Models\{Plan,Department};
use App\Services\{QualitySentinel,CalendarHelper};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ValidationConsoleController extends Controller
{
    public function index(Request $request, QualitySentinel $sentinel)
    {
        $months=['july','august','september','october','november','december','january','february','march','april','may','june'];
        $filters=$request->validate(['year'=>'nullable|integer|min:1900|max:9999','month'=>'nullable|in:'.implode(',',$months),
            'department_id'=>'nullable|integer|exists:departments,id','severity'=>'nullable|in:critical,warning,review',
            'rule'=>'nullable|in:missing,outlier,zero_plan,approval','search'=>'nullable|string|max:200','download'=>'nullable|in:csv']);
        $year=(int)($filters['year'] ?? Plan::where('status','approved')->max('year') ?? now()->year);
        $month=$filters['month'] ?? strtolower(now()->format('F'));
        $scan=$sentinel->scan($year,$month,isset($filters['department_id']) ? (int)$filters['department_id'] : null);
        $stats=$scan['stats']; $departmentRows=$scan['departments'];
        $alerts=collect($scan['alerts'])->filter(function($a) use($filters) {
            if (!empty($filters['severity']) && $a['severity']!==$filters['severity']) return false;
            if (!empty($filters['rule']) && $a['rule']!==$filters['rule']) return false;
            return empty($filters['search']) || str_contains(mb_strtolower($a['department'].' '.$a['activity'].' '.$a['code'].' '.$a['title']),mb_strtolower($filters['search']));
        })->values();
        if (($filters['download'] ?? '')==='csv') {
            return response()->streamDownload(function() use($alerts) {
                $out=fopen('php://output','w');
                fputcsv($out,['Severity','Rule','Department','Activity code','Activity','Period','Frequency','Finding'],',','"','');
                foreach($alerts as $a) {
                    $cells=[$a['severity'],$a['rule'],$a['department'],$a['code'],$a['activity'],$a['period'],$a['frequency'],$a['detail']];
                    fputcsv($out,array_map(fn($v)=>preg_match('/^[=+@\-\t\r]/',(string)$v) ? "'".$v : $v,$cells),',','"','');
                }
                fclose($out);
            },"quality-sentinel-{$year}-{$month}.csv",['Content-Type'=>'text/csv; charset=UTF-8']);
        }
        $total=$alerts->count(); $page=LengthAwarePaginator::resolveCurrentPage();
        $alerts=new LengthAwarePaginator($alerts->forPage($page,20)->values(),$total,20,$page,['path'=>$request->url(),'query'=>$request->except('page','download')]);
        $departments=Department::where('is_active',true)->orderBy('name')->get();
        $years=collect(CalendarHelper::getAppYears())->merge(Plan::where('status','approved')->distinct()->pluck('year'))->push($year)->unique()->sortDesc();
        return view('quality.console',compact('year','month','months','years','filters','stats','departmentRows','alerts','departments'));
    }
}
