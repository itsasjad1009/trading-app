import {test,expect} from '@playwright/test';
import {execFileSync} from 'node:child_process';
import {mkdirSync} from 'node:fs';
test('protected auth and legal layouts across nine sizes',async({page},info)=>{
 test.skip(info.project.name!=='1440x900'||process.env.RESPONSIVE_PHASE==='before');
 const sizes=[{width:320,height:640},{width:375,height:667},{width:390,height:844},{width:430,height:932},{width:768,height:1024},{width:1024,height:768},{width:1366,height:768},{width:1440,height:900},{width:1920,height:1080}];
 async function capture(path:string,name:string){for(const size of sizes){await page.setViewportSize(size);await page.goto(path);await expect(page.locator('h1')).toBeVisible();expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBeTruthy();const dir=`test-artifacts/responsive-review/after/${size.width}x${size.height}/auth`;mkdirSync(dir,{recursive:true});await page.screenshot({path:`${dir}/${name}.png`,fullPage:true});}}
 const email=`responsive-auth-${Date.now()}@example.test`;await page.goto('/register');await page.getByLabel('Full Name').fill('Responsive Auth Fixture');await page.getByLabel('Email').fill(email);await page.getByLabel('Password *',{exact:true}).fill('AuthFixture!123');await page.getByLabel('Confirm Password').fill('AuthFixture!123');await page.getByRole('button',{name:'Create Account'}).click();await expect(page).toHaveURL(/verify/);
 await capture('/email/verify','verify-email');execFileSync('php',['-d','extension=intl','artisan','demo:verify',email]);await capture('/user/confirm-password','confirm-password');
 await page.goto('/profile');await page.getByRole('button',{name:'Logout',exact:true}).click();await expect(page).toHaveURL('http://127.0.0.1:8000/');
 await capture(`/reset-password/responsive-invalid-token?email=${encodeURIComponent(email)}`,'reset-password');
 for(const name of ['privacy','terms','risk'])await capture(`/legal/${name}`,`legal-${name}`);
 // Enable MFA only on this newly created local fixture; no secrets leave PHP.
 execFileSync('php',['-d','extension=intl','artisan','tinker','--execute',`abort_unless(app()->environment('local','testing'),403); $fixture=App\\Models\\User::where('email','${email}')->firstOrFail(); app(Laravel\\Fortify\\Actions\\EnableTwoFactorAuthentication::class)($fixture); $fixture->forceFill(['two_factor_confirmed_at'=>now()])->save();`]);
 await page.goto('/login');await page.getByLabel('Email').fill(email);await page.getByLabel('Password *',{exact:true}).fill('AuthFixture!123');await page.getByRole('button',{name:'Sign In',exact:true}).click();await expect(page).toHaveURL(/two-factor-challenge/);await capture('/two-factor-challenge','two-factor-challenge');
});

