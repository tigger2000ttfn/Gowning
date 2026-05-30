<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class JobTitle extends Model
{
    use Auditable;
    protected $table = 'job_titles';
    protected $fillable = ['name', 'code', 'is_active', 'sort'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
}
