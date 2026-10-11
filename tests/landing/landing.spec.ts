import {test,expect} from '@playwright/test';
import fs from 'node:fs';
test('landing geometry, links, motion and overflow',async({page},info)=>{
 const errors:string[]=[];page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='error')errors.push(m.text())});
 await page.goto('/');await page.evaluate(()=>document.fonts.ready);await page.emulateMedia({reducedMotion:'reduce'});
 const frozen=await page.addStyleTag({content:'.particles{visibility:hidden!important}'});
 const dir=`test-artifacts/landing-page-fidelity/${process.env.LANDING_PHASE||'after'}/${info.project.name}`;fs.mkdirSync(dir,{recursive:true});
 const measures=await page.evaluate(()=>Object.fromEntries(['.announcement','.hero h1','.hero > p','.hero-actions','.hero-actions .primary','.hero-actions .outline','.hero-stats','.hero-stats > div','.public-section','.features','.features article','.icon-tile','.features h3','.features article p','.promos','.promo','footer'].map(s=>{const e=document.querySelector(s)!;const r=e.getBoundingClientRect(),c=getComputedStyle(e);return[s,{x:r.x,y:r.y,width:r.width,height:r.height,font:c.fontSize,lineHeight:c.lineHeight,padding:c.padding,gap:c.gap}]})));
 fs.writeFileSync(`${dir}/measurements.json`,JSON.stringify({viewport:page.viewportSize(),dpr:await page.evaluate(()=>devicePixelRatio),fonts:await page.evaluate(()=>document.fonts.status),measures},null,2));
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBeTruthy();
 await page.screenshot({path:`${dir}/full.png`,fullPage:true});for(const [label,selector]of [['hero','.hero'],['statistics','.hero-stats'],['features','.features'],['banners','.promos'],['footer','footer']])await page.locator(selector).screenshot({path:`${dir}/${label}.png`});
 await frozen.evaluate(e=>e.remove());await page.locator('.hero').screenshot({path:`${dir}/hero-with-particles.png`});
 await expect(page.getByRole('link',{name:'Get Started',exact:true})).toHaveAttribute('href','/register');await expect(page.getByRole('link',{name:'Sign In',exact:true})).toHaveAttribute('href','/login');
 await page.getByRole('link',{name:'Sign In',exact:true}).click();await expect(page).toHaveURL(/\/login$/);await page.goto('/');await page.getByRole('link',{name:'Get Started',exact:true}).click();await expect(page).toHaveURL(/\/register$/);expect(errors).toEqual([]);
});

