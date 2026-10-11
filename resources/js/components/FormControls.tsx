import type {ComponentProps} from 'react';

export function Input({className='',...props}:ComponentProps<'input'>){
 return <input className={`form-input ${className}`} {...props}/>;
}
export function Select({className='',...props}:ComponentProps<'select'>){
 return <select className={`form-select ${className}`} {...props}/>;
}
