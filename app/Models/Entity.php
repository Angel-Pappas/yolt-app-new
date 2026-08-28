<?php

namespace App\Models;

use Database\Factories\EntityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $vat_number
 */
#[Fillable(['name', 'vat_number'])]
class Entity extends Model
{
    /** @use HasFactory<EntityFactory> */
    use HasFactory, SoftDeletes;
}
