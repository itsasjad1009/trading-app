import {useForm} from '@inertiajs/react';
import {useState} from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import {ArrowUp,ArrowDown} from 'lucide-react';
import {Errors} from './ui';
import {Input} from './FormControls';
export default function TradeControls({pairId,balance,symbol='selected pair',payouts={60:7000,90:7200,120:7500,180:7800,360:8000}}:{pairId:number;balance:string;symbol?:string;payouts?:Record<number,number>}){
 const form=useForm({amount:'100',duration:60,direction:'buy',pair_id:pairId,request_key:crypto.randomUUID()});
 const [confirmation,setConfirmation]=useState<string|null>(null);
 const valid=Number(form.data.amount)>=100&&Number(form.data.amount)<=Math.min(10000,Number(balance))&&/^\d+(\.\d{1,2})?$/.test(form.data.amount);
 function place(direction:string){if(!valid||form.processing)return;form.transform(data=>({...data,pair_id:pairId,direction}));form.post('/trade',{preserveScroll:true,onSuccess:()=>{form.setData('request_key',crypto.randomUUID());setConfirmation(null);}});}
 function requestOrder(direction:string){if(Number(form.data.amount)>=1000)setConfirmation(direction);else place(direction);}
 return <div className="trade-controls">
  <label htmlFor="trade-amount">Amount (USDT) — Min 100</label>
  <div className="preset-grid" role="group" aria-label="Amount presets">{[100,500,1000,5000,10000].map(n=><button type="button" key={n} aria-pressed={Number(form.data.amount)===n} className={Number(form.data.amount)===n?'selected':''} onClick={()=>form.setData('amount',String(n))}>{n}</button>)}</div>
  <Input id="trade-amount" aria-label="Trade amount" aria-describedby="order-risk" aria-invalid={!valid} type="number" inputMode="decimal" min="100" max="10000" step="0.01" value={form.data.amount} onChange={e=>form.setData('amount',e.target.value)}/>
  <label>Duration</label><div className="preset-grid durations" role="group" aria-label="Expiry duration">{[60,90,120,180,360].map(n=><button type="button" key={n} aria-pressed={form.data.duration===n} className={form.data.duration===n?'selected':''} onClick={()=>form.setData('duration',n)}>{n}s</button>)}</div>
  <p id="order-risk" className="muted small">Win: +{payouts[form.data.duration]/100}% of stake. Loss: stake. Tie: refund.<br/>Virtual funds · server-set execution.</p>
  <Errors errors={form.errors}/>{!valid&&<p className="error">Enter 100–10,000 USDT within your available balance.</p>}
  <div className="trade-actions"><button disabled={!valid||form.processing} className="buy" onClick={()=>requestOrder('buy')}><ArrowUp/>{form.processing?'Placing…':'BUY'}</button><button disabled={!valid||form.processing} className="sell" onClick={()=>requestOrder('sell')}><ArrowDown/>{form.processing?'Placing…':'SELL'}</button></div>
  <div className="order-available"><span>Available</span><strong>{Number(balance).toLocaleString('en-US',{minimumFractionDigits:2})} USDT</strong></div>
  <Dialog.Root open={confirmation!==null} onOpenChange={open=>{if(!open&&!form.processing)setConfirmation(null);}}><Dialog.Portal><Dialog.Overlay className="overlay"/><Dialog.Content className="order-dialog"><Dialog.Title>Confirm demo position</Dialog.Title><Dialog.Description>{confirmation?.toUpperCase()} · {symbol} · {form.data.amount} virtual USDT · {form.data.duration}s. A losing position loses the entire stake.</Dialog.Description><Errors errors={form.errors}/><div className="trade-actions"><Dialog.Close asChild><button className="button outline" disabled={form.processing}>Cancel</button></Dialog.Close><button className="button primary" disabled={form.processing||!valid} onClick={()=>confirmation&&place(confirmation)}>{form.processing?'Placing…':'Confirm position'}</button></div></Dialog.Content></Dialog.Portal></Dialog.Root>
 </div>;
}
