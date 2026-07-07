<?php

namespace FestivalMapper\Models;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property int         $id
 * @property string      $name
 * @property int         $year
 * @property string|null $map_image_path
 * @property int|null    $map_width
 * @property int|null    $map_height
 * @property string|null $description
 */
class Festival extends Model
{
    protected $table = 'festival_mapper_festivals';

    protected $appends = [
        'map_image_url',
    ];

    protected $fillable = [
        'name',
        'year',
        'map_image_path',
        'map_width',
        'map_height',
        'description',
    ];

    public function calibrationPoints(): HasMany
    {
        return $this->hasMany(CalibrationPoint::class, 'festival_id');
    }

    public function pins(): HasMany
    {
        return $this->hasMany(Pin::class, 'festival_id');
    }

    public function activeLayers(): HasMany
    {
        return $this->hasMany(MapLayer::class, 'festival_id')->where('is_active', true);
    }

    public function mapLayers(): HasMany
    {
        return $this->hasMany(MapLayer::class, 'festival_id');
    }

    public function getMapImageUrlAttribute(): ?string
    {
        if (! $this->map_image_path) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(config('festival-mapper.disk', 'public'));

        return $disk->url($this->map_image_path);
    }
}
