import {useEffect,useRef,useState} from 'react';
import {createChart,CandlestickSeries,AreaSeries,HistogramSeries,ColorType,type UTCTimestamp,type IChartApi,type ISeriesApi} from 'lightweight-charts';
interface Candle {time:number;open:number;high:number;low:number;close:number;volume:number}
export default function MarketChart({pairId,interval,view,live=false,symbol}:{pairId:number;interval:number;view:'candle'|'area';live?:boolean;symbol?:string}){
 const element=useRef<HTMLDivElement>(null);const api=useRef<{chart:IChartApi;candle:ISeriesApi<'Candlestick'>;area:ISeriesApi<'Area'>;volume:ISeriesApi<'Histogram'>}|null>(null);
 const [status,setStatus]=useState('Loading chartâ€¦');const [last,setLast]=useState<Candle|null>(null);
 useEffect(()=>{
  if(!element.current)return;
  const chart=createChart(element.current,{autoSize:true,handleScroll:{vertTouchDrag:false,horzTouchDrag:true},handleScale:{pinch:true},layout:{background:{type:ColorType.Solid,color:'#0b0e14'},textColor:'#a1adc0',fontSize:11},grid:{vertLines:{color:'#1a202a'},horzLines:{color:'#1a202a'}},rightPriceScale:{borderColor:'#303745',scaleMargins:{top:.08,bottom:.22}},timeScale:{borderColor:'#303745',timeVisible:true,secondsVisible:false,rightOffset:4},crosshair:{mode:0},localization:{locale:'en-GB',priceFormatter:(p:number)=>p.toLocaleString('en-US',{maximumFractionDigits:p<1?6:2})}});
  const candle=chart.addSeries(CandlestickSeries,{upColor:'#00c4d3',downColor:'#e82f3a',wickUpColor:'#00c4d3',wickDownColor:'#e82f3a',borderVisible:false});
  const area=chart.addSeries(AreaSeries,{lineColor:'#4f93ff',topColor:'#3975d528',bottomColor:'#3975d502',lineWidth:2});
  const volume=chart.addSeries(HistogramSeries,{priceFormat:{type:'volume'},priceScaleId:'',lastValueVisible:false,priceLineVisible:false});volume.priceScale().applyOptions({scaleMargins:{top:.86,bottom:0}});
  api.current={chart,candle,area,volume};return()=>{api.current=null;chart.remove();};
 },[]);
 useEffect(()=>{api.current?.candle.applyOptions({visible:view==='candle'});api.current?.area.applyOptions({visible:view==='area'});},[view]);
 useEffect(()=>{
  let alive=true;let first=true;let timer:ReturnType<typeof setTimeout>;let controller:AbortController;
  setStatus('Loading chartâ€¦');setLast(null);api.current?.candle.setData([]);api.current?.area.setData([]);api.current?.volume.setData([]);
  async function update(){
   if(document.hidden){timer=setTimeout(update,10000);return;}
   controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),8000);
   try{
    const response=await fetch(`${live?'/market/live/candles':'/market/candles'}?pair_id=${pairId}&interval=${interval}`,{signal:controller.signal,headers:{Accept:'application/json'}});if(!response.ok)throw new Error('Market unavailable');
    const result=await response.json();const data:Candle[]=result.candles.map((c:Candle)=>({...c,open:Number(c.open),high:Number(c.high),low:Number(c.low),close:Number(c.close),volume:Number(c.volume)}));if(!data.length)throw new Error('History unavailable');if(!alive||!api.current)return;
    const series=data.map(c=>({...c,time:c.time as UTCTimestamp}));api.current.candle.setData(series);api.current.area.setData(data.map(c=>({time:c.time as UTCTimestamp,value:c.close})));api.current.volume.setData(data.map(c=>({time:c.time as UTCTimestamp,value:c.volume,color:c.close>=c.open?'#00c4d348':'#e82f3a48'})));
    if(first){api.current.chart.timeScale().fitContent();first=false;}setLast(data.at(-1)??null);setStatus(live?`Binance Spot · ${result.health?.state??'DISCONNECTED'}${result.missing_count?' · History gaps':''}`:'Demo Market Data · Connected');
   }catch{if(alive)setStatus('Disconnected · last data may be stale');}
   finally{clearTimeout(timeout);if(alive)timer=setTimeout(update,10000);}
  }
  const stream=(e:Event)=>{const detail=(e as CustomEvent).detail;if(!live||detail.kind!=='candle'||interval!==60||!api.current)return;const c=detail.payload.candles.find((r:{symbol:string})=>r.symbol===symbol);if(!c||first)return;const row={time:Math.floor(c.open_ms/1000) as UTCTimestamp,open:Number(c.open),high:Number(c.high),low:Number(c.low),close:Number(c.close),volume:Number(c.volume)};try{api.current.candle.update(row);api.current.area.update({time:row.time,value:row.close});api.current.volume.update({time:row.time,value:row.volume,color:row.close>=row.open?'#00c4d348':'#e82f3a48'});setLast(row);const chartUpdated=performance.now();window.dispatchEvent(new CustomEvent('aster-chart-timing',{detail:{symbol,trace:c.timing,distribution:detail.payload.distribution,browser_received_ms:detail.browser_received_ms,chart_api_updated_ms:Date.now(),receive_to_chart_api_ms:chartUpdated-detail.browser_received_monotonic}}));}catch{/* older stream candle cannot replace the current chart */}};
  window.addEventListener('aster-market',stream);void update();return()=>{alive=false;controller?.abort();clearTimeout(timer);window.removeEventListener('aster-market',stream);};
 },[pairId,interval,live,symbol]);
 return <div className="chart-wrap"><div ref={element} className="chart-canvas" data-testid="market-chart" role="img" aria-label={`${live?'Binance Spot':'Simulated'} price chart. Latest OHLC values are provided below.`}/><div className="chart-status"><span role="status">{status}</span><span>UTC</span></div>{last&&<p className="sr-only">Latest candle: open {last.open}, high {last.high}, low {last.low}, close {last.close}. Prices use virtual USDT.</p>}</div>;
}
