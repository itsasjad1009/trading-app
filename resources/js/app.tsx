import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
const pages=import.meta.glob('./pages/*.tsx');
createInertiaApp({ title:title=>`${title} · Aster Exchange`,resolve:async name=>{ const loader=pages[`./pages/${name}.tsx`];if(!loader)throw new Error(`Unknown page ${name}`);return await loader() as never; },setup({el,App,props}){createRoot(el).render(<App {...props}/>);},progress:{color:'#00DAE8'} });
