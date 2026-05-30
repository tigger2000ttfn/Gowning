<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class SamplingSite extends Model
{
    use Auditable;
    protected $table = 'sampling_sites';
    protected $fillable = ['name', 'code', 'is_active', 'sort'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
}
