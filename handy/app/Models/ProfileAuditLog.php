<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileAuditLog extends Model
{
    use HasFactory;

    protected $table = 'profile_audit_logs';

    protected $fillable = ['entity_type','entity_id','action','actor_user_id','data'];

    protected $casts = ['data' => 'array'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
