<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class TrainingClassCatalog extends Model
{
    use Auditable;
    protected $table = 'training_class_catalog';
    protected $fillable = ['name', 'code', 'is_active', 'sort'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
}
