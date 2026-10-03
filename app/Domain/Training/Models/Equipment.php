<?php

namespace App\Domain\Training\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'slug'])]
#[WithoutTimestamps]
class Equipment extends Model
{
    protected $table = 'equipment';
}
