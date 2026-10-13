<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'module', 'description'];

    public function roles()
    {
        return $this->belongsToMany(\App\Models\Role::class, 'role_permissions');
    }
}
