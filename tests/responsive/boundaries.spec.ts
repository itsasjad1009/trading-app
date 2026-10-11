import {test,expect} from '@playwright/test';
import {execFileSync} from 'node:child_process';
import {mkdirSync} from 'node:fs';
test('breakpoint boundaries preserve layout and navigation',async({page},info)=>{
 test.skip(process.env.RESPONSIVE_PHASE==='before'||info.project.name!=='1440x900');
 const email=`responsive-boundary-${Date.now()}@example.test`;await page.goto('/register');await page.getByLabel('Full Name').fill('Responsive Boundary Fixture');await page.getByLabel('Email').fill(email);await page.getByLabel('Password *',{exact:true}).fill('BoundaryFixture!123');await page.getByLabel('Confirm Password').fill('BoundaryFixture!123');await page.getByRole('button',{name:'Create Account'}).click();await expect(page).toHaveURL(/verify|dashboard/);execFileSync('php',['-d','extension=intl','artisan','demo:verify',email]);
 const dir='test-artifacts/responsive-review/after/boundaries';mkdirSync(dir,{recursive:true});
 for(const width of [374,375,431,550,551,639,640,767,768,850,851,1023,1024,1199,1200,1279,1280,1439,1440,1535,1536]){
  await page.setViewportSize({width,height:900});
  for(const path of ['/','/login','/dashboard','/trade']){
   if(path==='/login')continue;await page.goto(path);if(path==='/trade')await expect(page.getByRole('status').filter({hasText:'Demo Market Data · Connected'})).toBeVisible();
   expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),`${path} at${width}`).toBeTruthy();
   if(path==='/dashboard'){await expect(page.locator(width<1024?'.mobile-bar':'.sidebar')).toBeVisible();}
  }
  await page.screenshot({path:`${dir}/trade-${width}.png`,fullPage:true});
 }
});
