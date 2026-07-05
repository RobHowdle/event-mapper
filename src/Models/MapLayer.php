<?php

namespace FestivalMapper\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int    $id
 * @property int    $festival_id
 * @property string $layer_key
 * @property string $name
 * @property bool   $is_active
 * @property int    $sort_order
 * @property array  $options
 */
class MapLayer extends Model
{
    protected $table = 'festival_mapper_map_layers';

    protected $fillable = [
        'festival_id',
        'layer_key',
        'name',
        'is_active',
        'sort_order',
        'options',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'options'    => 'array',
    ];

    public function festival(): BelongsTo
    {
        return $this->belongsTo(Festival::class, 'festival_id');
    }
}
