import { Link, usePage } from '@inertiajs/react';
import { BarChart3, Check, Copy } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import type { Pagination,Shared } from '../types';
export function useRoutes(){const {routes}=usePage<Shared>().props;return (name:string)=>routes[name]??`/${name}`;}
export function Card({children,className=''}:{children:ReactNode;className?:string}){return <section className={`card ${className}`}>{children}</section>;}
export function Empty({text,detail}:{text:string;detail?:string}){return <div className="empty"><BarChart3 size={38}/><p>{text}</p>{detail&&<small>{detail}</small>}</div>;}
export function CopyButton({value}:{value:string}){const [copied,setCopied]=useState(false);const [error,setError]=useState('');return <><button type="button" className="icon-button" aria-label="Copy to clipboard" onClick={async()=>{try{await navigator.clipboard.writeText(value);setCopied(true);setTimeout(()=>setCopied(false),2000);}catch{setError('Clipboard unavailable. Select and copy the text.');}}}>{copied?<Check/>:<Copy/>}</button>{error&&<span role="alert">{error}</span>}</>;}
export function Pager<T>({page}:{page:Pagination<T>}){return <nav className="pagination" aria-label="Pagination">{page.links.map((link,i)=>link.url?<Link key={i} href={link.url} className={link.active?'selected':''}>{link.label.replace(/&laquo;/g,'«').replace(/&raquo;/g,'»')}</Link>:<span key={i}>{link.label.replace(/&laquo;/g,'«').replace(/&raquo;/g,'»')}</span>)}</nav>;}
export function Errors({errors}:{errors:Record<string,string>}){return <>{Object.entries(errors).map(([key,value])=><p className="error" role="alert" key={key}>{value}</p>)}</>;}
export const money=(minor:number)=>new Intl.NumberFormat('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}).format(minor/100);
export const price=(value:number)=>new Intl.NumberFormat('en-US',{maximumFractionDigits:value<1?6:2}).format(value);
