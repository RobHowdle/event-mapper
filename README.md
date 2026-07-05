# Festival Mapper

A reusable Laravel package providing an interactive mapping engine for temporary events such as music festivals.

Festival Mapper is **not** a GIS system. It is a coordinate transformation engine that keeps multiple map layers synchronised through a single internal coordinate system. Every user interaction resolves to one internal coordinate; different layers simply display that coordinate differently.

---

## Requirements

- PHP 8.2+
- Laravel 12.x
- A modern browser (Vue 3 frontend)

---

## Installation

### 1. Require the package

**From Packagist (once published):**

```bash
composer require festival-mapper/festival-mapper
```

**For local development (path repository):**

Add the following to your Laravel app's `composer.json` before requiring:

```json
"repositories": [
    {
        "type": "path",
        "url": "../multi-layer-map"
    }
]
```

```bash
composer require festival-mapper/festival-mapper:*
```

### 2. Publish assets

```bash
# Config
php artisan vendor:publish --tag=festival-mapper-config

# Migrations (optional — they auto-load without publishing)
php artisan vendor:publish --tag=festival-mapper-migrations

# Vue components
php artisan vendor:publish --tag=festival-mapper-assets
```

### 3. Run migrations

```bash
php artisan migrate
```

This creates four tables:

| Table                                | Purpose                                   |
| ------------------------------------ | ----------------------------------------- |
| `festival_mapper_festivals`          | Festival records with map image metadata  |
| `festival_mapper_map_layers`         | Which layers are active for each festival |
| `festival_mapper_calibration_points` | Pixel ↔ internal coordinate anchors       |
| `festival_mapper_pins`               | Pins stored as internal coordinates       |

---

## Configuration

`config/festival-mapper.php` after publishing:

```php
return [
    // URL prefix for all API routes
    'route_prefix' => env('FESTIVAL_MAPPER_ROUTE_PREFIX', 'api/festival-mapper'),

    // Middleware applied to all routes
    'middleware' => ['api'],

    // Filesystem disk used to store map images
    'disk' => env('FESTIVAL_MAPPER_DISK', 'public'),

    // Coordinate transformer implementation
    'transformer' => \FestivalMapper\Transforms\AffineTransformer::class,
];
```

---

## Usage

### Step 1 — Create a festival

```http
POST /api/festival-mapper/festivals
Content-Type: application/json

{
    "name": "Download Festival",
    "year": 2026,
    "description": "Optional description"
}
```

Note the `id` returned — it is used in every subsequent request.

---

### Step 2 — Upload the map image

```http
POST /api/festival-mapper/festivals/{id}/map
Content-Type: multipart/form-data

map_image: <image file, max 20 MB>
```

The package stores the image path and automatically records the pixel dimensions.

---

### Step 3 — Add calibration points

Calibration anchors link a known pixel position on the image to a normalised internal coordinate (values in the range `0.0`–`1.0`).

You need **at least two** anchors. For best accuracy, pick two real landmarks that are far apart on your map.

```http
POST /api/festival-mapper/festivals/{id}/calibration
Content-Type: application/json

{
    "pixel_x": 0,
    "pixel_y": 0,
    "internal_x": 0.0,
    "internal_y": 0.0,
    "label": "Top-left corner"
}
```

```http
POST /api/festival-mapper/festivals/{id}/calibration
Content-Type: application/json

{
    "pixel_x": 4000,
    "pixel_y": 3000,
    "internal_x": 1.0,
    "internal_y": 1.0,
    "label": "Bottom-right corner"
}
```

---

### Step 4 — Activate layers

```http
POST /api/festival-mapper/festivals/{id}/layers/festival-image/activate
```

Built-in layer IDs:

| ID               | Description                                         |
| ---------------- | --------------------------------------------------- |
| `festival-image` | Displays the uploaded map image                     |
| `what3words`     | Resolves a What3Words address (requires a provider) |
| `elevation`      | Retrieves elevation data (requires a provider)      |

---

### Step 5 — Drop a pin

Pins can be created from a pixel click or from a direct internal coordinate. Either way, only the internal coordinate is stored, keeping pins layer-independent.

```http
POST /api/festival-mapper/festivals/{id}/pins
Content-Type: application/json

{
    "pixel_x": 1200,
    "pixel_y": 800,
    "label": "Main Stage",
    "metadata": { "capacity": 50000 }
}
```

Or supply the internal coordinate directly:

```http
POST /api/festival-mapper/festivals/{id}/pins
Content-Type: application/json

{
    "internal_x": 0.30,
    "internal_y": 0.27,
    "label": "Main Stage"
}
```

---

### Step 6 — Resolve a coordinate across all active layers

When a pin is placed or selected, call this endpoint to receive the data every active layer needs to render that coordinate:

```http
POST /api/festival-mapper/festivals/{id}/layers/resolve
Content-Type: application/json

{
    "internal_x": 0.30,
    "internal_y": 0.27
}
```

**Response:**

```json
{
	"coordinate": {"x": 0.3, "y": 0.27},
	"layers": [
		{
			"id": "festival-image",
			"name": "Festival Map",
			"render": {"component": "FestivalImageLayer"},
			"data": {
				"image_url": "festival-mapper/maps/abc123.jpg",
				"width": 4000,
				"height": 3000,
				"pin_pixel": {"x": 1200.0, "y": 810.0}
			}
		}
	]
}
```

---

## Frontend (Vue 3)

### Register the plugin

```js
// resources/js/app.js
import {createApp} from "vue";
import FestivalMapperPlugin from "./vendor/festival-mapper";
import App from "./App.vue";

const app = createApp(App);
app.use(FestivalMapperPlugin);
app.mount("#app");
```

### Use the component

```html
<FestivalMap :festival-id="1" api-base="/api/festival-mapper" />
```

| Prop          | Type     | Default                | Description             |
| ------------- | -------- | ---------------------- | ----------------------- |
| `festival-id` | `Number` | required               | The festival ID to load |
| `api-base`    | `String` | `/api/festival-mapper` | API route prefix        |

**Interactions:**

- **Click** the map to drop a pin at that position.
- **Drag** a pin to move it.
- **Double-click** a pin to delete it.
- **Layer switcher** buttons change the active view. Pin positions remain identical across all layers.

---

## Extending the Package

### Adding a custom layer

Implement `LayerInterface`:

```php
use FestivalMapper\Contracts\LayerInterface;
use FestivalMapper\ValueObjects\InternalCoordinate;

class WeatherLayer implements LayerInterface
{
    public function id(): string   { return 'weather'; }
    public function name(): string { return 'Weather'; }

    public function getData(InternalCoordinate $coordinate): array
    {
        // Fetch weather data for this coordinate.
        return ['temperature' => 18.5, 'condition' => 'Partly cloudy'];
    }

    public function render(): array
    {
        return ['component' => 'WeatherLayer'];
    }
}
```

Register it in your `AppServiceProvider`:

```php
use FestivalMapper\Engines\LayerEngine;

public function boot(LayerEngine $layerEngine): void
{
    $layerEngine->register(new WeatherLayer());
}
```

Activate it for a festival:

```http
POST /api/festival-mapper/festivals/{id}/layers/weather/activate
```

---

### Swapping the coordinate transformer

Implement `CoordinateTransformerInterface` and rebind it:

```php
use FestivalMapper\Contracts\CoordinateTransformerInterface;

$this->app->bind(CoordinateTransformerInterface::class, MyAdvancedTransformer::class);
```

---

### Adding an elevation provider

```php
use FestivalMapper\Contracts\ElevationProviderInterface;
use FestivalMapper\ValueObjects\InternalCoordinate;

class OpenElevationProvider implements ElevationProviderInterface
{
    public function getElevation(InternalCoordinate $coordinate): ?float
    {
        // Call the Open-Elevation API.
        return 312.5;
    }
}
```

```php
$this->app->bind(ElevationProviderInterface::class, OpenElevationProvider::class);
```

---

### Adding a What3Words provider

```php
use FestivalMapper\Contracts\What3WordsProviderInterface;
use FestivalMapper\ValueObjects\InternalCoordinate;

class What3WordsApiProvider implements What3WordsProviderInterface
{
    public function getAddress(InternalCoordinate $coordinate): ?string
    {
        // Convert internal coord to lat/lng using festival bounds,
        // then call the What3Words API.
        return 'filled.count.soap';
    }
}
```

```php
$this->app->bind(What3WordsProviderInterface::class, What3WordsApiProvider::class);
```

---

## API Reference

| Method   | Endpoint                                      | Description                                       |
| -------- | --------------------------------------------- | ------------------------------------------------- |
| `GET`    | `/festivals`                                  | List all festivals                                |
| `POST`   | `/festivals`                                  | Create a festival                                 |
| `GET`    | `/festivals/{id}`                             | Get a festival with all relations                 |
| `PATCH`  | `/festivals/{id}`                             | Update a festival                                 |
| `DELETE` | `/festivals/{id}`                             | Delete a festival                                 |
| `POST`   | `/festivals/{id}/map`                         | Upload a map image                                |
| `GET`    | `/festivals/{id}/calibration`                 | List calibration points                           |
| `POST`   | `/festivals/{id}/calibration`                 | Add a calibration point                           |
| `PATCH`  | `/festivals/{id}/calibration/{point}`         | Update a calibration point                        |
| `DELETE` | `/festivals/{id}/calibration/{point}`         | Delete a calibration point                        |
| `GET`    | `/festivals/{id}/pins`                        | List all pins                                     |
| `POST`   | `/festivals/{id}/pins`                        | Create a pin                                      |
| `PATCH`  | `/festivals/{id}/pins/{pin}`                  | Move or update a pin                              |
| `DELETE` | `/festivals/{id}/pins/{pin}`                  | Delete a pin                                      |
| `GET`    | `/festivals/{id}/layers`                      | List all registered layers and their active state |
| `POST`   | `/festivals/{id}/layers/resolve`              | Resolve a coordinate across all active layers     |
| `POST`   | `/festivals/{id}/layers/{layerId}/activate`   | Activate a layer for a festival                   |
| `POST`   | `/festivals/{id}/layers/{layerId}/deactivate` | Deactivate a layer for a festival                 |

All routes are prefixed with the value of `route_prefix` from config (default: `/api/festival-mapper`).

---

## Testing

```bash
composer install
vendor/bin/phpunit --testdox
```

---

## Architecture

```
src/
├── Contracts/          # Interfaces — swap any implementation without touching the core
├── Engines/
│   ├── CoordinateEngine   # Pixel ↔ internal coordinate translation
│   ├── LayerEngine        # Layer plugin registry and dispatch
│   └── PinEngine          # Pin CRUD
├── Layers/             # Built-in layer implementations
├── Models/             # Eloquent models
├── Transforms/         # AffineTransformer (default) — replaceable
├── ValueObjects/       # InternalCoordinate, PixelCoordinate, CalibrationAnchor
└── Http/Controllers/   # REST API
```

### Internal coordinate system

All coordinates are normalised floats in the range `[0, 1]` relative to the map image (`0,0` = top-left, `1,1` = bottom-right). This keeps coordinates image-resolution-independent and means pins require no recalculation when the image is resized or replaced.

---

## Licence

MIT
