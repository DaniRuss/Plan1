<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceAttachment extends Model
{
    protected $fillable = [
        'department_id', 'year', 'month', 'week_number', 'frequency',
        'file_path', 'original_name', 'mime_type', 'file_size', 'uploaded_by',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
