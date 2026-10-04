import test from 'node:test';
import assert from 'node:assert/strict';
import {locationLinks, coordinatesText, validLocation} from '../../resources/js/utils/locationLinks.js';
import {imageCalibration, pixelToGeo, geoToPixel} from '../../resources/js/utils/imageCalibration.js';
const location={latitude:52.833038,longitude:-1.385773};
test('copied links encode coordinates directly and route to map services without YNF',()=>{
 const links=locationLinks(location,'Android');
 assert.equal(coordinatesText(location),'52.833038, -1.385773');
 assert.equal(new URL(links.share).searchParams.get('query'),'52.833038,-1.385773');
 assert(links.open.startsWith('geo:52.833038,-1.385773'));
 assert.equal(locationLinks(location,'iPhone').open,links.apple);
 assert.equal(locationLinks(location,'Safari','MacIntel',5).open,links.apple);
 assert.equal(locationLinks(location,'Chrome').open,links.google);
 assert.equal(new URL(links.apple).searchParams.get('ll'),'52.833038,-1.385773');
 assert.equal(validLocation({latitude:NaN,longitude:0}),false);
 assert.equal(validLocation({latitude:null,longitude:0}),false);
 assert.throws(()=>coordinatesText({latitude:91,longitude:0}));
});
test('cached affine conversion preserves rotation and shear and round-trips without requests',()=>{
 const festival={map_width:1600,map_height:1000};
 const corners=[{latitude:52.84,longitude:-1.4},{latitude:52.8384,longitude:-1.384},{latitude:52.83,longitude:-1.398}];
 const transform=imageCalibration(festival,corners);
 const point=pixelToGeo(transform,950,380);
 const pixel=geoToPixel(transform,point);
 assert(Math.abs(pixel.x-950)<1e-6);assert(Math.abs(pixel.y-380)<1e-6);
 assert.deepEqual(pixelToGeo(transform,0,0),corners[0]);
 assert.equal(imageCalibration(festival,[corners[0],corners[0],corners[0]]),null);
});
