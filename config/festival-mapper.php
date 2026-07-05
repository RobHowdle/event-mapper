<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | All Festival Mapper API routes will be registered under this prefix.
    |
    */
    'route_prefix' => env('FESTIVAL_MAPPER_ROUTE_PREFIX', 'api/festival-mapper'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware applied to all Festival Mapper routes. Defaults to the
    | standard Laravel API middleware group.
    |
    */
    'middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk used to store map images.
    |
    */
    'disk' => env('FESTIVAL_MAPPER_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Coordinate Transformer
    |--------------------------------------------------------------------------
    |
    | The implementation to use for coordinate transforms.
    | Must implement CoordinateTransformerInterface.
    |
    */
    'transformer' => \FestivalMapper\Transforms\AffineTransformer::class,

];
