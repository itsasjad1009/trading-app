import {Link} from '@inertiajs/react';import type{ReactNode}from'react';
export default function AuthLayout({children,title,description}:{children:ReactNode;title:string;description:string}){return <main className="auth-page"><Link href="/" className="brand">aster✧</Link><h1>{title}</h1><p className="muted">{description}</p>{children}</main>;}
