# Location pins, what3words and elevation colours

Click a map in Locations & Pins to place the selection marker and fill its coordinates. Click again to move it. Manual coordinates remain optional. The public viewer also supports selecting an arbitrary point and copying or sharing its what3words address.

## what3words through YN Auth

YNF binds the package's `FestivalMapper\Contracts\What3WordsProvider` interface to its `YnAuthWhat3WordsProvider`. Address, grid and enabled-status requests go through the existing authenticated `YnAuthClient`, then the application-authenticated `/api/what3words/config`, `/address` and `/grid` endpoints in YN Auth. The provider key stays in YN Auth; it is never returned to YNF or the browser. Both YN Auth and YNF need the accompanying code updates.

YN Auth reads `WHAT3WORDS_API_KEY` (also accepts existing `WHAT3WORDS_KEY` or `W3W_API_KEY`). Its key must have entitlement to `convert-to-3wa` and `grid-section`; this may require a paid what3words plan. Clear configuration caches after deployment. Do not put the key into a Vite variable or copy it into YNF.

Other host applications can bind their own implementation to the interface. The package's default direct provider remains available for standalone installations using the existing `festival-mapper.what3words.key` configuration.

The geographic layer keeps its `geo-map` identity and activation setting, but is labelled what3words. It uses OpenStreetMap underneath the official what3words grid. The grid appears from zoom 18 and only requests bounding boxes smaller than the provider's four-kilometre diagonal limit.

## Elevation overlay

Activate Topography in the mapper admin's Layers section for each festival that needs it. A calibrated artwork footprint is required. The server transforms all four image corners to geographic coordinates, samples a fixed 10 by 10 grid covering those bounds, and obtains all 100 elevations in one Open Topo Data batch request. Results are cached for a day and regenerated when calibration, image dimensions or provider configuration change.

The sequential colour gradient and legend use the minimum and maximum valid sample heights for that site. The range stays fixed while panning, zooming, changing opacity or switching layers. Flat sites use the middle palette colour. Missing data stays transparent; no fake zero elevations are introduced. Bilinear interpolation between samples renders a smooth estimated surface. The legend states approximate sample spacing so visual smoothness is not confused with survey accuracy.

Elevation opacity adjusts only the colour overlay. The festival artwork can be shown underneath it; the underlying topographic basemap remains available outside the artwork. Clicking a point still obtains that point's estimated elevation independently. Raster colours are dataset measurements and do not change with branding; controls inherit the festival theme.

Configurable environment variables:

- `FESTIVAL_MAPPER_TOPOGRAPHY_TILES`: XYZ basemap template; default `https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png`.
- `FESTIVAL_MAPPER_ELEVATION_ENABLED`: defaults to true.
- `FESTIVAL_MAPPER_ELEVATION_ENDPOINT`: defaults to `https://api.opentopodata.org/v1`; may point to a self-hosted Open Topo Data service.
- `FESTIVAL_MAPPER_ELEVATION_DATASET`: defaults to `aster30m` for global coverage.

Uncached elevation calls are serialized and limited to one per second and 1,000 per day across the application to respect the default public endpoint's limits. The viewer shows an actionable unavailable state if a provider fails, calibration is missing, elevation is missing or a limit is reached. Festival artwork, navigation and pin editing remain usable.

Provider references:

- https://developer.what3words.com/public-api/docs
- https://www.opentopodata.org/api/
- https://opentopomap.org/
