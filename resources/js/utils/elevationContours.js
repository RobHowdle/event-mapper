// Estimated contours from the sampled surface; missing cells produce no lines.
export function contourInterval(terrain) {
    const target = (terrain.maximum-terrain.minimum)/8;
    if(target<=0)return 1;
    const power=10**Math.floor(Math.log10(target));
    return [1,2,5,10].find(value=>value*power>=target)*power;
}
export function elevationContours(terrain) {
    const interval=contourInterval(terrain), groups=[];
    for(let height=Math.ceil(terrain.minimum/interval)*interval;height<terrain.maximum;height+=interval){
        const segments=[];
        for(let row=0;row<terrain.rows-1;row++)for(let column=0;column<terrain.columns-1;column++){
            const corners=[[column,row],[column+1,row],[column+1,row+1],[column,row+1]].map(([x,y])=>({x,y,height:terrain.heights[y*terrain.columns+x]}));
            if(corners.some(point=>point.height==null))continue;
            for(const indices of [[0,1,2],[0,2,3]]){
                const triangle=indices.map(i=>corners[i]), intersections=[];
                for(let edge=0;edge<3;edge++){
                    const a=triangle[edge],b=triangle[(edge+1)%3];
                    if((a.height<height && b.height>=height)||(b.height<height && a.height>=height)){
                        const t=(height-a.height)/(b.height-a.height);
                        const x=a.x+(b.x-a.x)*t,y=a.y+(b.y-a.y)*t,bounds=terrain.bounds;
                        intersections.push([bounds.north-(bounds.north-bounds.south)*y/(terrain.rows-1),bounds.west+(bounds.east-bounds.west)*x/(terrain.columns-1)]);
                    }
                }
                if(intersections.length===2)segments.push(intersections);
            }
        }
        if(segments.length)groups.push({height:Number(height.toFixed(2)),segments});
    }
    return groups;
}
