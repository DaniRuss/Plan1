<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReportingTeam extends Model
{
    protected $guarded = ['id'];
    public function department() { return $this->belongsTo(Department::class); }
    public function coordinator() { return $this->belongsTo(User::class, 'coordinator_id'); }
    public function backup() { return $this->belongsTo(User::class, 'backup_id'); }
}
