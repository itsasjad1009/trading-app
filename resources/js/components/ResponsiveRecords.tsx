import type {ReactNode} from 'react';
import {useEffect,useState} from 'react';
export function RecordCards({children,label}:{children:ReactNode;label:string}){return <div className="record-cards" aria-label={label}>{children}</div>}
export function RecordField({label,children}:{label:string;children:ReactNode}){return <div><dt>{label}</dt><dd>{children}</dd></div>}
export function PrivateFilePreview({file,uploaded=false}:{file:File|null;uploaded?:boolean}){
 const [url,setUrl]=useState<string|null>(null);
 useEffect(()=>{if(!file||!['image/png','image/jpeg'].includes(file.type)){setUrl(null);return;}const next=URL.createObjectURL(file);setUrl(next);return()=>URL.revokeObjectURL(next);},[file]);
 return file?<div className="file-preview">{url&&<img src={url} alt="Selected sample document preview"/>}<span>{file.name}</span><small>{uploaded?'Submitted privately for review':'Selected locally · Not uploaded yet'} · {Math.ceil(file.size/1024)} KB</small></div>:null;
}
