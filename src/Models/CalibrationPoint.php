<?php

namespace FestivalMapper\Models;

use FestivalMapper\ValueObjects\CalibrationAnchor;
use FestivalMapper\ValueObjects\InternalCoordinate;
use FestivalMapper\ValueObjects\PixelCoordinate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $festival_id
 * @property float       $pixel_x
 * @property float       $pixel_y
 * @property float       $internal_x
 * @property float       $internal_y
 * @property string|null $label
 */
class CalibrationPoint extends Model
{
    protected $table = 'festival_mapper_calibration_points';

    protected $fillable = [
        'festival_id',
        'pixel_x',
        'pixel_y',
        'internal_x',
        'internal_y',
        'label',
    ];

    protected $casts = [
        'pixel_x'    => 'float',
        'pixel_y'    => 'float',
        'internal_x' => 'float',
        'internal_y' => 'float',
    ];

    public function festival(): BelongsTo
    {
        return $this->belongsTo(Festival::class, 'festival_id');
    }

    public function toAnchor(): CalibrationAnchor
    {
        return new CalibrationAnchor(
            new PixelCoordinate($this->pixel_x, $this->pixel_y),
            new InternalCoordinate($this->internal_x, $this->internal_y),
        );
    }
}
