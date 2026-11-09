<?php
namespace App\Console\Commands;

use App\Models\StrategicObjective;
use App\Services\ObjectiveWorkbookImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RestoreWorkbookTargets extends Command
{
    protected $signature = 'objectives:restore-workbook-targets {file : Original imported workbook path} {--year= : Imported target year} {--apply : Fill missing targets; default is preview only}';
    protected $description = 'Restore missing formula targets from an original workbook without replacing existing values';

    public function handle(ObjectiveWorkbookImport $parser): int
    {
        if (!is_file($this->argument('file')) || !ctype_digit((string)$this->option('year'))) {
            $this->error('Provide an existing workbook and --year=TARGET_YEAR.');
            return self::FAILURE;
        }
        $rows=$parser->parse($this->argument('file'));
        $changes=[];
        DB::transaction(function () use ($rows,&$changes) {
            foreach ($rows as $row) {
                $matches=StrategicObjective::where('target_year',(int)$this->option('year'))->where('code',$row['code'])
                    ->where('description',$row['description'])->where('unit',$row['unit'])->where('data_source','like','Workbook:%')->lockForUpdate()->get();
                if ($matches->count()!==1) continue;
                $objective=$matches->first();
                $updates=[];
                foreach (array_keys(ObjectiveWorkbookImport::PERIOD_COLUMNS) as $field) {
                    if ($objective->{$field}===null && $row[$field]!==null) $updates[$field]=$row[$field];
                }
                if (!$updates) continue;
                $changes[]=['id'=>$objective->id,'code'=>$objective->code,'before'=>array_fill_keys(array_keys($updates),null),'targets'=>$updates];
            }
            if ($this->option('apply') && $changes) {
                // Persist recovery evidence before touching any target values.
                $backup='database-backups/workbook-targets-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
                if (!Storage::disk('local')->put($backup,json_encode($changes,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))) {
                    throw new \RuntimeException('Recovery record could not be saved. No targets were changed.');
                }
                foreach ($changes as $change) DB::table('strategic_objectives')->where('id',$change['id'])->update($change['targets']);
                $this->info('Recovery record: '.$backup);
            }
        });
        foreach ($changes as $change) $this->line($change['code'].': '.json_encode($change['targets']));
        $this->info(count($changes).' objectives '.($this->option('apply') ? 'repaired.' : 'would be repaired. Add --apply to save.'));
        $this->line('Existing plan items and approved plans are unchanged; use the normal plan revision workflow for published plans.');
        return self::SUCCESS;
    }
}
