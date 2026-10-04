# Standard map and optional providers

The standard Map layer uses OpenStreetMap. what3words is disabled by default: no provider configuration, address or grid requests are made, and the viewer displays no what3words unavailable messages. Pin placement, calibration, layer comparison, directions and estimated elevation remain available without a paid what3words plan. The stored YN Auth key does not need removing.

Set `FESTIVAL_MAPPER_WHAT3WORDS_ENABLED=true` in a host application's environment only to opt into what3words when its provider has suitable API entitlement, then clear configuration caches. Existing `geo-map` activation settings are preserved. When enabled, the public viewer labels the layer what3words; otherwise it is Map.

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

Elevation opacity adjusts only the colour overlay. A neutral street basemap provides location context. Estimated contours with height labels are shown by default; colour shading and festival artwork are optional and initially off. Clicking a point still obtains that point's estimated elevation independently. Raster colours are dataset measurements and do not change with branding; controls inherit the festival theme.

Configurable environment variables:

- `FESTIVAL_MAPPER_TOPOGRAPHY_TILES`: XYZ basemap template; default `https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png`.
- `FESTIVAL_MAPPER_ELEVATION_ENABLED`: defaults to true.
- `FESTIVAL_MAPPER_ELEVATION_ENDPOINT`: defaults to `https://api.opentopodata.org/v1`; may point to a self-hosted Open Topo Data service.
- `FESTIVAL_MAPPER_ELEVATION_DATASET`: defaults to `aster30m` for global coverage.

Uncached elevation calls are serialized and limited to one per second and 1,000 per day across the application to respect the default public endpoint's limits. The viewer shows an actionable unavailable state if a provider fails, calibration is missing, elevation is missing or a limit is reached. Festival artwork, navigation and pin editing remain usable.

Provider references:

- https://developer.what3words.com/public-api/docs
- https://www.opentopodata.org/api/
- https://opentopomap.org/

## Diagnostics

The viewer maps known provider failure codes to fixed, safe messages for missing keys, invalid keys, unsupported plans or quota, request limits, missing YN Auth endpoints, application authentication and connection failures. It does not expose provider response bodies or exception messages. Grid load failures are visible beside the map; the three-metre grid otherwise appears from zoom 18. Coordinates remain available to administrators placing pins and are hidden from the public selected-location panel.

An existing key alone does not enable the what3words conversion and grid APIs: the Free plan does not include them. Configure the correct entitlement in the what3words account if the viewer reports a plan failure.
