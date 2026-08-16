<?php

namespace FestivalMapper\Models;

use FestivalMapper\ValueObjects\GeoCoordinate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $festival_id
 * @property float       $latitude
 * @property float       $longitude
 * @property string      $label
 * @property array       $metadata
 */
class Pin extends Model
{
    protected $table = 'festival_mapper_pins';

    protected $fillable = [
        'festival_id',
        'latitude',
        'longitude',
        'label',
        'metadata',
    ];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
        'metadata'  => 'array',
    ];

    public function festival(): BelongsTo
    {
        return $this->belongsTo(Festival::class, 'festival_id');
    }

    public function toCoordinate(): GeoCoordinate
    {
        return new GeoCoordinate(
            latitude: $this->latitude,
            longitude: $this->longitude,
        );
    }
}
