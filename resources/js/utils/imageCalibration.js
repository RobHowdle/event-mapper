// Derive the server's fitted affine transform from three resolved image corners.
export function imageCalibration(festival, corners) {
    const width = Number(festival.map_width), height = Number(festival.map_height);
    if (width <= 0 || height <= 0 || corners.length !== 3) return null;
    const [origin,right,bottom] = corners;
    const a=(right.longitude-origin.longitude)/width, b=(bottom.longitude-origin.longitude)/height;
    const d=(right.latitude-origin.latitude)/width, e=(bottom.latitude-origin.latitude)/height;
    const determinant=a*e-b*d;
    if(![a,b,d,e,origin.longitude,origin.latitude].every(Number.isFinite) || Math.abs(determinant)<1e-15)return null;
    return {a,b,c:Number(origin.longitude),d,e,f:Number(origin.latitude),determinant};
}
export function pixelToGeo(transform, x, y) {
    const latitude=transform.d*x+transform.e*y+transform.f, longitude=transform.a*x+transform.b*y+transform.c;
    if(!Number.isFinite(latitude) || !Number.isFinite(longitude) || Math.abs(latitude)>90 || Math.abs(longitude)>180)throw new Error('Invalid geographic position');
    return {latitude,longitude};
}
export function geoToPixel(transform, location) {
    const longitude=Number(location.longitude)-transform.c, latitude=Number(location.latitude)-transform.f;
    return {x:(transform.e*longitude-transform.b*latitude)/transform.determinant, y:(-transform.d*longitude+transform.a*latitude)/transform.determinant};
}
