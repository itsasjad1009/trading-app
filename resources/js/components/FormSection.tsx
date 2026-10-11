import type {ReactNode} from 'react';
export function FormField({label,children}:{label:string;children:ReactNode}){
 return <label className="form-field"><span>{label}</span>{children}</label>;
}
export function FormSection({title,description,status,children}:{title:string;description:string;status?:ReactNode;children:ReactNode}){
 return <section className="form-section"><header className="form-section-heading"><h4>{title}</h4>{status}</header><p className="form-description muted">{description}</p>{children}</section>;
}
