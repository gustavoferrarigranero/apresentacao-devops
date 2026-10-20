<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeploymentProbe extends Model
{
    protected $fillable = ['token', 'release', 'processed_at'];

    protected function casts(): array
    {
        return ['processed_at' => 'immutable_datetime'];
    }
}
