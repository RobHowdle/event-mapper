export function validLocation(location) {
    return location && location.latitude != null && location.longitude != null &&
        Number.isFinite(Number(location.latitude)) && Number.isFinite(Number(location.longitude)) &&
        Math.abs(Number(location.latitude)) <= 90 && Math.abs(Number(location.longitude)) <= 180;
}
export function coordinatesText(location) {
    if (!validLocation(location)) throw new Error('Invalid location');
    return `${Number(location.latitude).toFixed(6)}, ${Number(location.longitude).toFixed(6)}`;
}
export function locationLinks(location, userAgent = '', platform = '', touchPoints = 0) {
    const coordinates = coordinatesText(location).replace(', ', ',');
    const encoded = encodeURIComponent(coordinates);
    const google = `https://www.google.com/maps/search/?api=1&query=${encoded}`;
    const apple = `https://maps.apple.com/?ll=${encoded}&q=${encoded}`;
    const geo = `geo:${coordinates}?q=${encoded}`;
    const isApple = /iPhone|iPad|iPod/i.test(userAgent) || (platform === 'MacIntel' && touchPoints > 1);
    return {google, apple, geo, share:google, open:isApple ? apple : /Android/i.test(userAgent) ? geo : google};
}
