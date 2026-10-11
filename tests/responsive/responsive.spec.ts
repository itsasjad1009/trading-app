import {test,expect} from '@playwright/test';
import {execFileSync} from 'node:child_process';
import {mkdirSync,writeFileSync} from 'node:fs';

test('capture public and customer pages with measured geometry',async({page},info)=>{
 const phase=process.env.RESPONSIVE_PHASE??'after';
 const viewport=info.project.use.viewport!;
 const dir=`test-artifacts/responsive-review/${phase}/${info.project.name}/empty`;mkdirSync(dir,{recursive:true});
 const errors:string[]=[];page.on('pageerror',e=>errors.push(e.message));
 const measurements:Record<string,unknown>={};
 async function capture(path:string){
  const start=Date.now();await page.goto(path);await expect(page.locator('body')).toBeVisible();await expect(page.getByRole('heading').first()).toBeVisible();
  if(path==='/trade')await expect(page.getByRole('status').filter({hasText:'Demo Market Data · Connected'})).toBeVisible();
  const geometry=await page.evaluate(()=>{
   const size=(selector:string)=>{const e=document.querySelector(selector);if(!e)return null;const b=e.getBoundingClientRect();const s=getComputedStyle(e);return {x:b.x,y:b.y,width:b.width,height:b.height,font:s.fontSize,padding:s.padding,radius:s.borderRadius};};
   return {viewport:{width:innerWidth,height:innerHeight},overflow:document.documentElement.scrollWidth>innerWidth,body:size('body'),sidebar:size('.sidebar'),title:size('h1'),input:size('input:not([type=file])'),card:size('.card'),chart:size('[data-testid=market-chart]'),buy:size('.buy'),nav:size('.nav-item'),empty:size('.empty'),heroAction:size('.hero-actions .primary'),feature:size('.features .card'),featureIcon:size('.features .icon-tile'),promo:size('.promo'),controls:Array.from(document.querySelectorAll('button,input:not([type=file]):not([type=checkbox]),select')).filter(e=>{const b=e.getBoundingClientRect();return b.width>0&&b.height>0;}).map(e=>({tag:e.tagName,label:e.getAttribute('aria-label')??e.textContent?.slice(0,30),height:e.getBoundingClientRect().height})),requests:performance.getEntriesByType('resource').length};
  });
  measurements[path]={...geometry,ready_ms:Date.now()-start};if(phase==='after'){expect(geometry.overflow,`${path} viewport overflow`).toBeFalsy();if(viewport.width<1024)expect(geometry.controls.filter(c=>c.height<43.5),`${path} touch controls`).toEqual([]);}
  await page.screenshot({path:`${dir}/${path==='/'?'home':path.slice(1)}.png`,fullPage:true});
 }
 for(const path of ['/','/login','/register','/forgot-password'])await capture(path);
 const email=`visual-${info.project.name}-${Date.now()}@example.test`;
 await page.goto('/register');await page.getByLabel('Full Name').fill('Visual Review Fixture');await page.getByLabel('Email').fill(email);await page.getByLabel('Password *',{exact:true}).fill('VisualFixture!123');await page.getByLabel('Confirm Password').fill('VisualFixture!123');if(phase==='after'){await page.getByLabel('Password *',{exact:true}).fill('weak');await page.getByLabel('Confirm Password').fill('weak');await page.getByRole('button',{name:'Create Account'}).click();await expect(page.getByRole('alert').first()).toBeVisible();await page.screenshot({path:`${dir}/register-validation.png`,fullPage:true});await page.getByLabel('Password *',{exact:true}).fill('VisualFixture!123');await page.getByLabel('Confirm Password').fill('VisualFixture!123');}await page.getByRole('button',{name:'Create Account'}).click();await expect(page).toHaveURL(/verify|dashboard/);
 execFileSync('php',['-d','extension=intl','artisan','demo:verify',email]);
 for(const path of ['/dashboard','/trade','/positions','/transactions','/deposit','/withdraw','/kyc','/support','/profile','/settings'])await capture(path);
 if(info.project.use.viewport!.width<1024){
  await page.getByRole('button',{name:'Open navigation'}).click();await expect(page.getByRole('dialog')).toBeVisible();await page.keyboard.press('Escape');await expect(page.getByRole('dialog')).not.toBeVisible();await expect(page.getByRole('button',{name:'Open navigation'})).toBeFocused();
 }
 if(phase==='after'){
  const populated=dir.replace('/empty','/populated');mkdirSync(populated,{recursive:true});
  await page.goto('/trade');await expect(page.getByRole('status').filter({hasText:'Demo Market Data · Connected'})).toBeVisible();
  await page.getByRole('button',{name:'360s',exact:true}).click();await page.getByRole('button',{name:'BUY',exact:true}).click();await expect(page.locator('main .record-card')).toHaveCount(1);
  await page.getByRole('button',{name:'60s',exact:true}).click();await page.getByRole('button',{name:'SELL',exact:true}).click();await expect(page.locator('main .record-card')).toHaveCount(2);
  for(const path of ['/trade','/positions','/dashboard','/transactions']){await page.goto(path);if(path==='/trade')await expect(page.getByRole('status').filter({hasText:'Demo Market Data · Connected'})).toBeVisible();await expect(page.locator('.record-card:visible,.table-wrap:visible').first()).toBeVisible();expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBeTruthy();await page.screenshot({path:`${populated}/${path.slice(1)}-active.png`,fullPage:true});}
  await page.goto('/support');await page.getByLabel('Message',{exact:true}).fill('Responsive sample support message');await page.getByRole('button',{name:'Send message'}).click();await expect(page.getByText('Responsive sample support message')).toBeVisible();await page.screenshot({path:`${populated}/support.png`,fullPage:true});
  await page.waitForTimeout(61000);await page.goto('/deposit');await page.getByRole('button',{name:'Submit Demo Funding Request'}).click();await expect(page.locator('.record-cards:visible,.table-wrap:visible').getByText('pending',{exact:true})).toBeVisible();await page.screenshot({path:`${populated}/deposit.png`,fullPage:true});
  await page.goto('/kyc');const image=Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1FoAAAAASUVORK5CYII=','base64');await page.getByLabel('ID front image',{exact:true}).setInputFiles({name:'sample-preview.png',mimeType:'image/png',buffer:image});await expect(page.locator('.file-preview img')).toBeVisible();await page.screenshot({path:`${populated}/kyc-local-preview.png`,fullPage:true});
  if(viewport.width===320||viewport.width===390){await page.goto('/trade');await expect(page.getByRole('status').filter({hasText:'Demo Market Data · Connected'})).toBeVisible();const canvas=await page.locator('.chart-canvas canvas').first().elementHandle();await page.setViewportSize({width:viewport.height,height:viewport.width});await expect(page.getByTestId('market-chart')).toBeVisible();expect(await canvas?.evaluate(e=>e.isConnected)).toBeTruthy();expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBeTruthy();await page.screenshot({path:`${populated}/trade-landscape.png`,fullPage:true});await page.setViewportSize(viewport);await page.goto('/support');await page.setViewportSize({width:viewport.width,height:420});await page.getByLabel('Message',{exact:true}).focus();await page.getByRole('button',{name:'Send message'}).scrollIntoViewIfNeeded();await expect(page.getByLabel('Message',{exact:true})).toBeInViewport();await page.screenshot({path:`${populated}/support-reduced-viewport.png`,fullPage:true});await page.setViewportSize(viewport);}
 }
 if(phase==='after'){
  execFileSync('php',['-d','extension=intl','artisan','demo:role',email,'super_admin']);
  await page.goto('/admin/operations-center');await expect(page.getByRole('button',{name:'Users',exact:true})).toBeVisible();
  await page.screenshot({path:`${dir}/staff-users.png`,fullPage:true,mask:[page.locator('.staff-records tbody')]});
  for(const module of ['Kyc','Deposits','Withdrawals','Support','Trades','Transactions']){
   await page.getByRole('button',{name:module,exact:true}).click();
   await expect(page.getByTestId('staff-module')).toHaveAttribute('data-module',module.toLowerCase());expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBeTruthy();
   await page.screenshot({path:`${dir}/staff-${module.toLowerCase()}.png`,fullPage:true,mask:[page.locator('.staff-records tbody')]});
  }
 }
 if(phase==='after'){await page.goto('/positions');await expect(page.locator('.badge.won:visible,.badge.lost:visible,.badge.tie:visible').first()).toBeVisible({timeout:90000});await page.screenshot({path:`${dir.replace('/empty','/populated')}/positions-completed.png`,fullPage:true});}
 writeFileSync(`${dir}/measurements.json`,JSON.stringify({pages:measurements,browserErrors:errors},null,2));expect(errors).toEqual([]);
});


