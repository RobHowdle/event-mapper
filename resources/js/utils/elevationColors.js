// A fixed sequential palette shared by the raster and its legend.
export const elevationStops = [[68, 1, 84], [59, 82, 139], [33, 145, 140], [94, 201, 98], [253, 231, 37]];
export const elevationGradient = `linear-gradient(to right, ${elevationStops.map(rgb => `rgb(${rgb.join(',')})`).join(', ')})`;
export function elevationColor(height, minimum, maximum) {
    const t = maximum === minimum ? .5 : Math.max(0, Math.min(1, (height-minimum)/(maximum-minimum)));
    const position = t*(elevationStops.length-1), index = Math.min(Math.floor(position), elevationStops.length-2), fraction = position-index;
    return elevationStops[index].map((channel, i) => Math.round(channel+(elevationStops[index+1][i]-channel)*fraction));
}
export function interpolatedHeight(terrain, x, y) {
    const column = Math.min(Math.floor(x), terrain.columns-2), row = Math.min(Math.floor(y), terrain.rows-2);
    const values = [terrain.heights[row*terrain.columns+column], terrain.heights[row*terrain.columns+column+1], terrain.heights[(row+1)*terrain.columns+column], terrain.heights[(row+1)*terrain.columns+column+1]];
    // Missing data must remain transparent, not become a false low elevation.
    if (values.some(value => value == null || !Number.isFinite(value))) return null;
    const dx = x-column, dy = y-row;
    return values[0]*(1-dx)*(1-dy)+values[1]*dx*(1-dy)+values[2]*(1-dx)*dy+values[3]*dx*dy;
}
