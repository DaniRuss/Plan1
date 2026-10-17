<?php
namespace App\Http\Middleware;

use App\Models\{ReportingTeam, ReportingSubmission, PlanItem};
use App\Services\SharedReporting;
use Closure;
use Illuminate\Http\Request;

class SharedReportingWorkspace
{
    public function handle(Request $request, Closure $next)
    {
        $name = $request->route()?->getName() ?? '';
        $names = ['weekly.entry','weekly.store','weekly.workflow','daily.entry','daily.store','daily.workflow','monthly.entry.form','monthly.entry.store','monthly.workflow','performance.edit','performance.update'];
        if (!in_array($name,$names,true)) return $next($request);
        $user = $request->user();
        if (!$user) return $next($request);
        $legacyPlan = null; $legacyMonth = null;
        if (str_starts_with($name,'performance.')) {
            $parameter = $request->route('plan');
            $legacyPlan = $parameter instanceof \App\Models\Plan ? $parameter : \App\Models\Plan::find($parameter);
            $period = strtolower((string)$request->route('period'));
            $legacyMonth = preg_replace('/^\d{4}-/','',$period);
            if (!$legacyPlan || !in_array($legacyMonth,['july','august','september','october','november','december','january','february','march','april','may','june'],true)) return $next($request);
        }
        $department = (int)($legacyPlan?->department_id ?? ($request->input('department_id') ?: $user?->department_id));
        if (!ReportingTeam::where('department_id',$department)->exists()) return $next($request);
        $service = app(SharedReporting::class);
        $service->authorizeView($user,$department);
        $frequency = $legacyPlan || str_starts_with($name,'monthly') ? 'monthly' : (str_starts_with($name,'daily') ? 'daily' : (\App\Models\Department::find($department)?->isDaily() ? 'daily' : 'weekly'));
        if (str_starts_with($name,'weekly') && in_array($request->input('frequency'),['weekly','daily'],true)) $frequency = $request->input('frequency');
        $key = ['department_id'=>$department,'year'=>(int)($legacyPlan?->year ?? $request->route('year') ?? $request->input('year')),
            'month'=>$legacyMonth ?? strtolower((string)($request->route('month') ?? $request->input('month'))),'frequency'=>$frequency,
            'week'=>$frequency === 'monthly' ? 0 : (int)($request->route('week') ?? $request->input('week'))];
        $submission = ReportingSubmission::where($key)->first();
        if (!$submission) {
            $ids = PlanItem::whereHas('plan',fn ($q) => $q->where('department_id',$department)->where('year',$key['year']))->forReportingFrequency($frequency)->pluck('id')->all();
            $legacy = $service->metricQuery($key,$ids)->get();
            // Preserve periods already in review. Only new/draft periods migrate.
            if ($legacy->contains(fn ($entry) => !in_array($entry->workflow_status,[null,'','draft'],true) || $entry->status === 'approved')) return $next($request);
        }
        if ($name === 'weekly.entry') return $next($request);
        $url = $submission ? route('shared-reporting.show',$submission) : route('shared-reporting.index',$key);
        if ($request->isMethod('GET')) return redirect($url);
        abort(409,'This department uses shared reporting. Open '.$url.' to edit or submit with version checks.');
    }
}
