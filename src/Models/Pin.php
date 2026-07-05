<?php

namespace FestivalMapper\Models;

use FestivalMapper\ValueObjects\InternalCoordinate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $festival_id
 * @property float       $internal_x
 * @property float       $internal_y
 * @property string      $label
 * @property array       $metadata
 */
class Pin extends Model
{
    protected $table = 'festival_mapper_pins';

    protected $fillable = [
        'festival_id',
        'internal_x',
        'internal_y',
        'label',
        'metadata',
    ];

    protected $casts = [
        'internal_x' => 'float',
        'internal_y' => 'float',
        'metadata'   => 'array',
    ];

    public function festival(): BelongsTo
    {
        return $this->belongsTo(Festival::class, 'festival_id');
    }

    public function toCoordinate(): InternalCoordinate
    {
        return new InternalCoordinate($this->internal_x, $this->internal_y);
    }
}
