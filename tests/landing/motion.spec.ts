import {test,expect} from '@playwright/test';
import fs from 'node:fs';
test('particle lifecycle respects motion preference and resumes',async({page},info)=>{
 test.skip(info.project.name!=='1440');await page.emulateMedia({reducedMotion:'reduce'});await page.goto('/');const canvas=page.locator('.particle-network');await expect(canvas).toBeVisible();await expect.poll(()=>canvas.evaluate((e:HTMLCanvasElement)=>e.width)).toBeGreaterThan(0);
 const first=await canvas.evaluate((e:HTMLCanvasElement)=>e.toDataURL());await page.waitForTimeout(250);expect(await canvas.evaluate((e:HTMLCanvasElement)=>e.toDataURL())).toBe(first);
 await page.emulateMedia({reducedMotion:'no-preference'});await page.waitForTimeout(250);expect(await canvas.evaluate((e:HTMLCanvasElement)=>e.toDataURL())).not.toBe(first);
 const cadence=await page.evaluate(()=>new Promise<{frames:number;elapsed:number;fps:number}>(resolve=>{let start=0,frames=0;const sample=(now:number)=>{if(!start)start=now;frames++;if(now-start<1000)requestAnimationFrame(sample);else resolve({frames,elapsed:now-start,fps:(frames-1)*1000/(now-start)})};requestAnimationFrame(sample)}));
 fs.writeFileSync('test-artifacts/landing-page-fidelity/animation-cadence.json',JSON.stringify(cadence,null,2));
 await page.setViewportSize({width:390,height:844});await expect.poll(()=>canvas.evaluate((e:HTMLCanvasElement)=>e.width)).toBe(390);await page.getByRole('link',{name:'Sign In',exact:true}).click();await expect(page.locator('.particle-network')).toHaveCount(0);
});
