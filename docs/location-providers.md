# Location pins, what3words and terrain

Click a map in Locations & Pins to place the selection marker and fill its coordinates. Click again to move it. Manual coordinates remain optional. The public viewer also supports selecting an arbitrary point and copying or sharing its what3words address.

The geographic layer keeps its existing `geo-map` identity and activation setting, but is now labelled what3words. It uses OpenStreetMap underneath the official what3words grid. The grid appears from zoom 18 and only requests bounding boxes smaller than the provider's four-kilometre diagonal limit. The API key stays on the server.

Set `WHAT3WORDS_API_KEY` in the host Laravel application's environment. The key must have entitlement to `convert-to-3wa` and `grid-section`. This may require a paid what3words plan. Run `php artisan optimize:clear` after configuring it. Never put the key into a Vite variable.

Activate the new Topography layer in the mapper admin's Layers section for each festival that needs it. It uses OpenTopoMap raster tiles and displays selected-point elevation from Open Topo Data. Terrain elevations are dataset estimates rather than surveying measurements. No elevation API key is required for the default public endpoint.

Configurable environment variables:

- `FESTIVAL_MAPPER_TOPOGRAPHY_TILES`: XYZ tile template; default `https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png`.
- `FESTIVAL_MAPPER_ELEVATION_ENABLED`: defaults to true.
- `FESTIVAL_MAPPER_ELEVATION_ENDPOINT`: defaults to `https://api.opentopodata.org/v1`; may point to a self-hosted Open Topo Data service.
- `FESTIVAL_MAPPER_ELEVATION_DATASET`: defaults to `aster30m` for global coverage.

Elevation results are cached for a day. Uncached provider calls are limited to one per second and 1,000 per day across the application to respect the default public endpoint's limits. The API returns an unavailable state if a provider fails, a key lacks entitlement, elevation is missing or a limit is reached. Festival artwork, map navigation and pin editing remain usable.

Provider references:

- https://developer.what3words.com/public-api/docs
- https://www.opentopodata.org/
- https://opentopomap.org/
