import test from 'node:test';
import assert from 'node:assert/strict';
import {elevationColor, elevationStops, interpolatedHeight} from '../../resources/js/utils/elevationColors.js';
test('legend colours match actual elevations, clamp bounds and handle flat sites', () => {
    assert.deepEqual(elevationColor(100,100,200), elevationStops[0]);
    assert.deepEqual(elevationColor(200,100,200), elevationStops.at(-1));
    assert.deepEqual(elevationColor(150,100,200), elevationStops[2]);
    assert.deepEqual(elevationColor(250,100,200), elevationStops.at(-1));
    assert.deepEqual(elevationColor(140,140,140), elevationStops[2]);
});
test('sample interpolation preserves missing terrain rather than showing it as zero', () => {
    const terrain={rows:2, columns:2, heights:[100,200,100,200]};
    assert.equal(interpolatedHeight(terrain,.5,.5),150);
    assert.equal(interpolatedHeight(terrain,1,1),200);
    assert.equal(interpolatedHeight({...terrain,heights:[100,null,100,200]},.5,.5),null);
});
