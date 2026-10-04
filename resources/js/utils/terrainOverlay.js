import L from 'leaflet';
import {elevationColor, interpolatedHeight} from './elevationColors';
export function terrainOverlay(map, terrain, opacity) {
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = 512;
    const context = canvas.getContext('2d'), pixels = context.createImageData(canvas.width, canvas.height);
    for (let y=0; y<canvas.height; y++) for (let x=0; x<canvas.width; x++) {
        const height = interpolatedHeight(terrain, x/(canvas.width-1)*(terrain.columns-1), y/(canvas.height-1)*(terrain.rows-1));
        if (height === null) continue;
        const index = (y*canvas.width+x)*4, color = elevationColor(height, terrain.minimum, terrain.maximum);
        pixels.data.set([...color, 255], index);
    }
    context.putImageData(pixels, 0, 0);
    const bounds = terrain.bounds;
    return L.imageOverlay(canvas.toDataURL(), [[bounds.south, bounds.west], [bounds.north, bounds.east]], {opacity, interactive:false, className:'mapper-terrain-overlay'}).addTo(map);
}
