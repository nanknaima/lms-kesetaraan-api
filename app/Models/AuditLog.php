<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_log';
    public $timestamps = false;
    protected $fillable = ['user_id', 'aksi', 'model', 'model_id', 'data_before', 'data_after', 'created_at'];
    protected $casts = [
        'data_before' => 'array',
        'data_after' => 'array',
        'created_at' => 'datetime',
    ];
}
