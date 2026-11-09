const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const context={module:{exports:{}}};
vm.runInNewContext(fs.readFileSync('public/js/analysis-graphs.js','utf8'), context);
const {buildGraph,mount}=context.module.exports;
const source={labels:['A','B','C','D','E'],datasets:[{label:'Current %',data:[0,25,50,100,null]},{label:'Reference %',data:[10,30,60,90,'']}]};
test('bar is default and switching never changes source values or missing data',()=>{
 const before=JSON.stringify(source);
 assert.equal(buildGraph(source).type,'bar');
 for(const type of ['pie','histogram','polygon','line','bar'])buildGraph(source,type);
 assert.equal(JSON.stringify(source),before);
 assert.equal(JSON.stringify(buildGraph(source,'line').data.datasets[0].data),'[0,25,50,100,null]');
});
test('distributions share bins and count each valid value once including zero and upper bound',()=>{
 for(const type of ['histogram','pie']){
  const graph=buildGraph(source,type);
  for(const dataset of graph.data.datasets)assert.equal(dataset.data.reduce((a,b)=>a+b,0),4);
 }
 const graph=buildGraph(source,'histogram');
 assert.equal(graph.data.datasets[0].barPercentage,1);
 assert.equal(graph.options.scales.y.title.text,'Number of results');
});
test('polygon uses numeric midpoints and closes at zero; empty and constant data are supported',()=>{
 const polygon=buildGraph(source,'polygon');
 assert.equal(polygon.type,'line');
 assert.equal(polygon.options.scales.x.type,'linear');
 assert.equal(polygon.data.datasets[0].data[0].y,0);
 assert.equal(polygon.data.datasets[0].data.at(-1).y,0);
 assert.equal(buildGraph({labels:[],datasets:[{data:[null,'',undefined]}]},'pie').data.datasets[0].data.reduce((a,b)=>a+b,0),0);
 assert.equal(buildGraph({labels:['A','B'],datasets:[{data:[5,5]}]},'histogram').data.datasets[0].data.reduce((a,b)=>a+b,0),2);
});

test('controls default to bar, rebuild on change and do not duplicate on repeated mount',()=>{
 const nodes=[];
 const element=tag=>({tag,children:[],dataset:{},appendChild(n){this.children.push(n);},setAttribute(){},addEventListener(event,fn){this[event]=fn;}});
 const canvas=element('canvas'); canvas.id='comparison';
 const host={parentElement:{insertBefore(node){nodes.push(node);}}}; canvas.parentElement=host;
 const doc={querySelectorAll:()=>[canvas],createElement:element};
 let active={data:source,config:{type:'bar'},options:{indexAxis:'y'},destroy(){this.destroyed=true;}};
 const old=active;
 const configs=[];
 function FakeChart(canvas,config){configs.push(config);active=this;this.destroy=()=>{};}
 FakeChart.getChart=()=>active;
 mount(FakeChart,doc);
 assert.equal(old.destroyed,true);
 assert.equal(configs[0].type,'bar');
 const select=nodes[0].children[0].children[0];
 assert.equal(select.children.length,5);
 select.value='polygon';select.change();
 assert.equal(configs.at(-1).type,'line');
 select.value='bar';select.change();
 assert.equal(JSON.stringify(configs.at(-1).data.datasets[0].data),'[0,25,50,100,null]');
 mount(FakeChart,doc);assert.equal(nodes.length,1);
});

test('numeric strings from server decimals are counted while blank strings remain missing',()=>{
 const graph=buildGraph({labels:['A','B','C'],datasets:[{data:['12.5','0','  ']}]},'histogram');
 assert.equal(graph.data.datasets[0].data.reduce((a,b)=>a+b,0),2);
});

test('readable horizontal bars wrap every word instead of clipping or shortening activity names',()=>{
 const label='K8 · Improve national import cargo transportation share with a detailed and fully visible activity description';
 const data={labels:[label],datasets:[{data:[80]}]};
 const graph=buildGraph(data,'bar',true,false,true);
 const ticks=graph.options.scales.y.ticks;
 assert.equal(typeof ticks?.callback,'function','Horizontal activity labels need a wrapping callback');
 const lines=ticks.callback.call({getLabelForValue:()=>label},0);
 assert.ok(Array.isArray(lines) && lines.length>1);
 assert.equal(lines.join(' '),label);
 assert.ok(lines.every(line=>line.length<=46));
 assert.equal(ticks.autoSkip,false);
 assert.equal(graph.data.labels[0],label);
 assert.equal(graph.data.datasets[0].data[0],80);
});

test('readable line graphs and tooltips retain complete labels when changing graph type',()=>{
 const label='Improve train punctuality and reliability rates from 50.7% to 72% through operational service improvements';
 const graph=buildGraph({labels:[label],datasets:[{data:[0,null]}]},'line',true,false,true);
 assert.equal(typeof graph.options.scales.x.ticks?.callback,'function','Line graph labels must stay readable after switching');
 const lines=graph.options.scales.x.ticks.callback.call({getLabelForValue:()=>label},0);
 assert.equal(lines.join(' '),label);
 assert.ok(lines.length>1);
 const title=graph.options.plugins.tooltip.callbacks.title([{dataIndex:0}]);
 assert.equal(title.join(' '),label);
 assert.equal(graph.data.datasets[0].data[0],0);
 assert.equal(graph.data.datasets[0].data[1],null);
});

test('frequency polygon tooltip keeps the point coordinate instead of an offset category range',()=>{
 const graph=buildGraph(source,'polygon',true,false,true);
 // Index 0 is the closing point; index 1 is the first real bin, centred at 10.
 const item={dataIndex:1,label:'10',parsed:{x:10,y:2}};
 const title=graph.options.plugins.tooltip?.callbacks?.title?.([item]) ?? [item.label];
 assert.equal(title.join(' '),'10');
});
