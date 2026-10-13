<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    protected $fillable = ['import_id', 'status', 'action', 'notes', 'user_id'];

    public function import() { return $this->belongsTo(Import::class); }
    public function user()   { return $this->belongsTo(User::class); }
}
