import L from 'leaflet';
// Keep the calibrated rotation and shear; a rectangular imageOverlay would lose both.
export function artworkOverlay(map, festival, corners) {
    const points = corners.map(geo => map.project([geo.latitude, geo.longitude], 0));
    const [origin, right, bottom] = points;
    const fourth = right.add(bottom).subtract(origin);
    const all = [...points, fourth];
    const minX = Math.min(...all.map(p => p.x)), minY = Math.min(...all.map(p => p.y));
    const maxX = Math.max(...all.map(p => p.x)), maxY = Math.max(...all.map(p => p.y));
    const width = Number(festival.map_width), height = Number(festival.map_height);
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', `0 0 ${maxX-minX} ${maxY-minY}`);
    const image = document.createElementNS(ns, 'image');
    image.setAttribute('href', festival.map_image_url || `/storage/${festival.map_image_path}`);
    image.setAttribute('width', width); image.setAttribute('height', height);
    image.setAttribute('preserveAspectRatio', 'none');
    image.setAttribute('transform', `matrix(${(right.x-origin.x)/width} ${(right.y-origin.y)/width} ${(bottom.x-origin.x)/height} ${(bottom.y-origin.y)/height} ${origin.x-minX} ${origin.y-minY})`);
    svg.appendChild(image);
    return L.svgOverlay(svg, L.latLngBounds(map.unproject([minX,maxY],0),map.unproject([maxX,minY],0)), {interactive: false, className:'mapper-artwork-overlay'}).addTo(map);
}
