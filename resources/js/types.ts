export interface User { id:number; name:string; email:string; phone:string|null; referral_code:string; status:string; created_at:string; email_verified_at:string|null }
export interface Shared { [key:string]:unknown; auth:{user:User|null;mfa_enabled:boolean;wallet:{available:string;reserved:string}|null}; flash:{success?:string;status?:string};routes:Record<string,string> }
export interface Market { id:number;symbol:string;name:string;price:number;change:number;volume:number;observed_at:string; source?:string; market_type?:string; unavailable?:boolean;stats_unavailable?:boolean;health?:{state:string;age_ms:number|null;last_update_ms:number|null} }
export interface Trade {id:number;symbol:string;direction:string;stake_minor:number;entry_price_units:number;expiry_price_units:number|null;profit_minor:number|null;payout_minor:number|null;opened_at:string;expires_at:string;status:string}
export interface Pagination<T> {data:T[];links:{url:string|null;label:string;active:boolean}[]}
