import {test,expect} from '@playwright/test';
import {execFileSync} from 'node:child_process';
import {mkdirSync,writeFileSync} from 'node:fs';

test('capture public and customer pages with measured geometry',async({page},info)=>{
 const phase=process.env.VISUAL_PHASE??'after';
 const dir=`test-artifacts/visual-review/${phase}/${info.project.name}`;mkdirSync(dir,{recursive:true});
 const errors:string[]=[];page.on('pageerror',e=>errors.push(e.message));
 const measurements:Record<string,unknown>={};
 async function capture(path:string){
  const start=Date.now();await page.goto(path);await expect(page.locator('body')).toBeVisible();await expect(page.getByRole('heading').first()).toBeVisible();
  if(path==='/trade')await expect(page.getByRole('status').filter({hasText:'Demo Market Data · Connected'})).toBeVisible();
  const geometry=await page.evaluate(()=>{
   const size=(selector:string)=>{const e=document.querySelector(selector);if(!e)return null;const b=e.getBoundingClientRect();const s=getComputedStyle(e);return {x:b.x,y:b.y,width:b.width,height:b.height,font:s.fontSize,padding:s.padding,radius:s.borderRadius};};
   return {viewport:{width:innerWidth,height:innerHeight},overflow:document.documentElement.scrollWidth>innerWidth,body:size('body'),sidebar:size('.sidebar'),title:size('h1'),input:size('input:not([type=file])'),card:size('.card'),chart:size('[data-testid=market-chart]'),buy:size('.buy'),nav:size('.nav-item'),empty:size('.empty'),heroAction:size('.hero-actions .primary'),feature:size('.features .card'),featureIcon:size('.features .icon-tile'),promo:size('.promo'),requests:performance.getEntriesByType('resource').length};
  });
  measurements[path]={...geometry,ready_ms:Date.now()-start};expect(geometry.overflow,`${path} viewport overflow`).toBeFalsy();
  await page.screenshot({path:`${dir}/${path==='/'?'home':path.slice(1)}.png`,fullPage:true});
 }
 for(const path of ['/','/login','/register','/forgot-password'])await capture(path);
 const email=`visual-${info.project.name}-${Date.now()}@example.test`;
 await page.goto('/register');await page.getByLabel('Full Name').fill('Visual Review Fixture');await page.getByLabel('Email').fill(email);await page.getByLabel('Password *',{exact:true}).fill('VisualFixture!123');await page.getByLabel('Confirm Password').fill('VisualFixture!123');await page.getByRole('button',{name:'Create Account'}).click();await expect(page).toHaveURL(/verify|dashboard/);
 execFileSync('php',['-d','extension=intl','artisan','demo:verify',email]);
 for(const path of ['/dashboard','/trade','/positions','/transactions','/deposit','/withdraw','/kyc','/support','/profile','/settings'])await capture(path);
 if(info.project.name==='mobile'){
  await page.getByRole('button',{name:'Open navigation'}).click();await expect(page.getByRole('dialog')).toBeVisible();await page.keyboard.press('Escape');await expect(page.getByRole('dialog')).not.toBeVisible();await expect(page.getByRole('button',{name:'Open navigation'})).toBeFocused();
 }
 if(phase.endsWith('after')){
  execFileSync('php',['-d','extension=intl','artisan','demo:role',email,'super_admin']);
  await page.goto('/admin/operations-center');await expect(page.getByRole('button',{name:'Users',exact:true})).toBeVisible();
  await page.screenshot({path:`${dir}/staff-users.png`,fullPage:true});
  for(const module of ['Kyc','Deposits','Withdrawals','Support','Trades','Transactions']){
   await page.getByRole('button',{name:module,exact:true}).click();
   await expect(page.getByTestId('staff-module')).toHaveAttribute('data-module',module.toLowerCase());
   await page.screenshot({path:`${dir}/staff-${module.toLowerCase()}.png`,fullPage:true});
  }
 }
 writeFileSync(`${dir}/measurements.json`,JSON.stringify({pages:measurements,browserErrors:errors},null,2));expect(errors).toEqual([]);
});

