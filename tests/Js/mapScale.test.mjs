import test from 'node:test';
import assert from 'node:assert/strict';
import {geographicScale,geographicZoom,imageGroundScale,imageZoom,validScale} from '../../resources/js/utils/mapScale.js';
import {imageCalibration} from '../../resources/js/utils/imageCalibration.js';

test('zoom conversion preserves ground scale across image and street coordinate systems',()=>{
 const calibration=imageCalibration({map_width:1600,map_height:1000},[{latitude:52.84,longitude:-1.4},{latitude:52.84,longitude:-1.384},{latitude:52.83,longitude:-1.4}]);
 const ground=imageGroundScale(calibration,800,500);
 for(const imageLevel of [-3,-1,0,2]){
  const scale=ground/2**imageLevel;
  const streetLevel=geographicZoom(52.835,scale);
  assert(Math.abs(geographicScale(52.835,streetLevel)-scale)<1e-9);
  assert(Math.abs(imageZoom(ground,geographicScale(52.835,streetLevel))-imageLevel)<1e-9);
 }
 const scale=geographicScale(52.835,16);
 assert(Math.abs(geographicScale(52.835,16.5)/scale-1/Math.SQRT2)<1e-12);
});

test('rotated calibration measures distance in both latitude and longitude',()=>{
 const transform={a:0,b:-.00001,c:-1.4,d:.00001,e:0,f:52.83};
 assert(imageGroundScale(transform,100,100)>1);
 assert.equal(imageGroundScale(null,0,0),null);
 for(const invalid of [null,0,-1,Infinity,NaN])assert.equal(validScale(invalid),false);
 assert.equal(validScale(.5),true);
});
