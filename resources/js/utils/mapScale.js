// Shared ground scale (metres per screen pixel), not incompatible Leaflet zoom numbers.
import {pixelToGeo} from './imageCalibration.js';
const circumference = 40075016.68557849;
export function geographicScale(latitude, zoom) {
    return circumference * Math.cos(Number(latitude) * Math.PI / 180) / (256 * 2 ** zoom);
}
export function geographicZoom(latitude, scale) {
    return Math.log2(geographicScale(latitude, 0) / scale);
}
export function imageGroundScale(calibration, x, y) {
    if (!calibration) return null;
    const start = pixelToGeo(calibration, x, y), end = pixelToGeo(calibration, x + 1, y);
    const radians = Math.PI / 180;
    const latitude = (start.latitude + end.latitude) / 2 * radians;
    return circumference / (2 * Math.PI) * Math.hypot((end.latitude - start.latitude) * radians, (end.longitude - start.longitude) * radians * Math.cos(latitude));
}
export function imageZoom(groundScale, screenScale) {
    return Math.log2(groundScale / screenScale);
}
export function validScale(value) { return Number.isFinite(value) && value > 0; }
