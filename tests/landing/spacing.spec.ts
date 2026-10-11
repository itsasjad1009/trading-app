import {test,expect} from '@playwright/test';
import fs from 'node:fs';
test('requested spacing and measured particle speed',async({page},info)=>{
 test.skip(!['1920','1440','1024','768','390'].includes(info.project.name));
 const errors:string[]=[];page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='error')errors.push(m.text())});
 await page.addInitScript(()=>{Math.random=()=>.5;const original=CanvasRenderingContext2D.prototype.arc;(window as any).particleSamples=[];CanvasRenderingContext2D.prototype.arc=function(x,y,r,a,b,c){if(this.canvas.classList.contains('particle-network'))(window as any).particleSamples.push({x,y,time:performance.now()});return original.call(this,x,y,r,a,b,c)}});
 await page.goto('/');await page.evaluate(()=>document.fonts.ready);await page.waitForTimeout(1100);
 const speed=await page.evaluate(()=>{const s=(window as any).particleSamples;const first=s[0],last=s[s.length-1];return Math.hypot(last.x-first.x,last.y-first.y)/((last.time-first.time)/1000)});expect(speed).toBeGreaterThan(28);expect(speed).toBeLessThan(32);
 const gaps=await page.evaluate(()=>{const box=(s:string)=>document.querySelector(s)!.getBoundingClientRect();const f=box('.features'),b=box('.promos'),d=box('.landing-disclosure'),footer=box('footer');const c=getComputedStyle(document.querySelector('.landing-disclosure')!);return{featureToBanner:b.top-f.bottom,bannerToDisclaimer:d.top+parseFloat(c.paddingTop)-b.bottom,disclaimerToFooter:footer.top-d.bottom+parseFloat(c.paddingBottom)}});
 const expected=info.project.name==='390'?[40,28,28]:info.project.name==='768'?[56,36,36]:[80,48,48];expect(Object.values(gaps)).toEqual(expected);expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBeTruthy();expect(errors).toEqual([]);
 const dir=`test-artifacts/landing-spacing/${info.project.name}`;fs.mkdirSync(dir,{recursive:true});fs.writeFileSync(`${dir}/measurements.json`,JSON.stringify({gaps,measuredSpeed:speed,previousSeededSpeed:12,ratio:speed/12},null,2));
 await page.emulateMedia({reducedMotion:'reduce'});await page.locator('.hero').screenshot({path:`${dir}/hero.png`});await page.locator('.public-section').screenshot({path:`${dir}/features-banners-disclaimer.png`});await page.locator('footer').screenshot({path:`${dir}/footer.png`});
});
