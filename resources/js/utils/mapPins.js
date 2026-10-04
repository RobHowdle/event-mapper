import L from 'leaflet';
export function pinLabel(pin) { return pin.label || 'Unnamed location'; }
export function safeLink(value) { try { const url = new URL(value); return ['http:', 'https:'].includes(url.protocol) ? url.href : null; } catch { return null; } }
export function drawPins(map, pins, coordinate, onSelect, color) {
    const group = L.layerGroup().addTo(map);
    for (const pin of pins) {
        const position = coordinate(pin);
        if (!position || !position.every(Number.isFinite)) continue;
        const label = document.createElement('span'); label.textContent = pinLabel(pin);
        L.circleMarker(position, {radius: 9, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1})
            .bindTooltip(label, {direction: 'top'}).on('click', event => { L.DomEvent.stopPropagation(event); onSelect(pin); }).addTo(group);
    }
    return group;
}
