import L from 'leaflet';
import {elevationContours} from './elevationContours';
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

export function terrainContours(map, terrain) {
    const group=L.layerGroup().addTo(map), center=[(terrain.bounds.north+terrain.bounds.south)/2,(terrain.bounds.west+terrain.bounds.east)/2];
    for(const contour of elevationContours(terrain)){
        group.addLayer(L.polyline(contour.segments,{color:'#334e44',weight:1.8,opacity:.85,interactive:false,className:'mapper-elevation-contour'}));
        const midpoints=contour.segments.map(segment=>[(segment[0][0]+segment[1][0])/2,(segment[0][1]+segment[1][1])/2]);
        const position=midpoints.reduce((best,point)=>map.distance(point,center)<map.distance(best,center)?point:best);
        group.addLayer(L.tooltip({permanent:true,direction:'center',className:'mapper-contour-label'}).setLatLng(position).setContent(`${contour.height} m`));
    }
    return group;
}
