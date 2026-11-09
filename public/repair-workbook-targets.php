<?php
/** Temporary admin-only browser repair. Upload beside public/index.php; delete after use. */
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

// If public files were moved in cPanel, set this to the Laravel folder containing artisan.
$projectRoot = dirname(__DIR__);
if (!is_file($projectRoot.'/vendor/autoload.php')) {
    http_response_code(500);
    exit('Set $projectRoot in this file to the Laravel project folder containing artisan.');
}
define('LARAVEL_START', microtime(true));
require $projectRoot.'/vendor/autoload.php';
$app = require $projectRoot.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
$request = Request::capture();
$app->make('router')->match(['GET','POST'], $request->getPathInfo(), function (Request $request) {
    abort_unless($request->user()?->isSystemAdmin() && $request->user()->is_active, 403, 'Log in to this application as an active system administrator first.');
    $output = '';
    $notice = '';
    $pending = $request->session()->get('workbook_target_repair');
    if ($request->isMethod('post')) {
        try {
            if ($request->input('action') === 'preview') {
                $validated = $request->validate(['workbook'=>'required|file|mimes:xlsx,xls|max:10240','year'=>'required|integer|min:2000|max:2200']);
                if ($pending) Storage::disk('local')->delete($pending['file']);
                $path = $request->file('workbook')->store('target-repairs', 'local');
                if (!$path) throw new \RuntimeException('Workbook upload failed.');
                $pending = ['file'=>$path,'year'=>(int)$validated['year'],'token'=>bin2hex(random_bytes(24))];
                $code = Artisan::call(\App\Console\Commands\RestoreWorkbookTargets::class, ['file'=>Storage::disk('local')->path($path),'--year'=>$pending['year']]);
                $output = Artisan::output();
                if ($code !== 0) throw new \RuntimeException($output);
                $request->session()->put('workbook_target_repair', $pending);
                $notice = 'Preview only. No database values have changed.';
            } elseif ($request->input('action') === 'apply') {
                abort_unless($pending && hash_equals($pending['token'], (string)$request->input('preview_token')), 419, 'Preview expired. Upload the workbook again.');
                $code = Artisan::call(\App\Console\Commands\RestoreWorkbookTargets::class, ['file'=>Storage::disk('local')->path($pending['file']),'--year'=>$pending['year'],'--apply'=>true]);
                $output = Artisan::output();
                if ($code !== 0) throw new \RuntimeException($output);
                Storage::disk('local')->delete($pending['file']);
                $request->session()->forget('workbook_target_repair');
                $pending = null;
                $notice = 'Repair completed. Refresh the objective details page. Delete this PHP file from the server after use.';
            } else abort(422, 'Unknown repair action.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $notice = implode(' ', $e->validator->errors()->all());
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $notice = 'Repair could not finish: '.$e->getMessage();
            $pending = null;
            $request->session()->forget('workbook_target_repair');
        }
    }
    $csrf = '<input type="hidden" name="_token" value="'.e(csrf_token()).'">';
    $apply = $pending ? '<form method="post">'.$csrf.'<input type="hidden" name="action" value="apply"><input type="hidden" name="preview_token" value="'.e($pending['token']).'"><button type="submit">Apply the previewed repair</button></form>' : '';
    return response('<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Repair missing workbook targets</title><style>body{font:15px system-ui;background:#f2f6f8;color:#203449;margin:0;padding:32px}main{max-width:980px;margin:auto;background:white;padding:28px;border-radius:12px}label{display:block;margin:18px 0}input{display:block;margin-top:8px;padding:8px;max-width:100%}button{background:#126c70;color:white;border:0;border-radius:6px;padding:12px 18px;cursor:pointer}pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#eef3f6;padding:18px;max-height:460px;overflow:auto}.notice{padding:12px;background:#e8f3ef;margin:18px 0}</style><main><h1>Repair missing workbook targets</h1><p>Upload the original imported workbook. Only missing monthly and quarterly objective targets will be filled. Existing target values, actual performance and published plan items stay unchanged.</p><form method="post" enctype="multipart/form-data">'.$csrf.'<input type="hidden" name="action" value="preview"><label>Original Excel workbook<input type="file" name="workbook" accept=".xlsx,.xls" required></label><label>Imported target year<input type="number" name="year" min="2000" max="2200" value="'.e((string)($pending['year'] ?? $request->input('year',2026))).'" required></label><button type="submit">Preview missing targets</button></form>'.($notice ? '<p class="notice">'.e($notice).'</p>' : '').($output ? '<pre>'.e($output).'</pre>' : '').$apply.'<p>A recovery record is saved in private storage when changes are applied. Remove this temporary repair file after use.</p></main></html>')
        ->header('Cache-Control','no-store, private')->header('X-Content-Type-Options','nosniff')->header('X-Frame-Options','DENY');
})->middleware(['web','auth']);
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
