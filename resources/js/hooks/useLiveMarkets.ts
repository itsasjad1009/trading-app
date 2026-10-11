import {useEffect,useState} from 'react';
import type {Market} from '../types';
export function normalizeMarkets(rows:Market[]):Market[]{return rows.map(r=>({...r,unavailable:r.price===null,stats_unavailable:r.change===null||r.volume===null,price:Number(r.price),change:Number(r.change),volume:Number(r.volume)}));}
function merge(current:Market[],incoming:Market[]):Market[]{return normalizeMarkets(incoming).map(row=>{const old=current.find(r=>r.id===row.id);return old&&old.health?.last_update_ms&&row.health?.last_update_ms&&old.health.last_update_ms>row.health.last_update_ms?old:row;});}
export default function useLiveMarkets(initial:Market[],enabled:boolean){
 const [markets,setMarkets]=useState(()=>normalizeMarkets(initial));
 useEffect(()=>setMarkets(old=>merge(old,initial)),[initial]);
 useEffect(()=>{if(!enabled)return;let alive=true;const controller=new AbortController();const update=(e:Event)=>{const detail=(e as CustomEvent).detail;if(detail.kind==='ticker')setMarkets(old=>merge(old,detail.payload.markets));};window.addEventListener('aster-market',update);
 const refresh=async()=>{try{const r=await fetch('/market/live/snapshot',{signal:controller.signal,headers:{Accept:'application/json'}});if(r.ok && alive){const payload=await r.json();setMarkets(old=>merge(old,payload.markets));}}catch{/* preserve last source-identified values */}};
 const timer=setInterval(()=>void refresh(),15000);const stale=setInterval(()=>setMarkets(rows=>rows.map(r=>({...r,health:r.health?.last_update_ms && Date.now()-r.health.last_update_ms>15000?{...r.health,state:'STALE'}:r.health}))),1000);
 return()=>{alive=false;controller.abort();window.removeEventListener('aster-market',update);clearInterval(timer);clearInterval(stale);};},[enabled]);return markets;
}
