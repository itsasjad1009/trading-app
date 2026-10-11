import {defineConfig} from '@playwright/test';
const sizes=[[320,640],[375,667],[390,844],[430,932],[768,1024],[1024,768],[1366,768],[1440,900],[1728,972],[1920,1080],[1886,909],[1900,880]];
export default defineConfig({testDir:'tests/landing',outputDir:'test-artifacts/landing-page-fidelity/results',timeout:60000,workers:2,use:{baseURL:'http://127.0.0.1:8000'},projects:[...sizes.map(([width,height])=>({name:`${width}`,use:{viewport:{width,height},deviceScaleFactor:1}})),{name:'calibrated125',use:{viewport:{width:1509,height:727},deviceScaleFactor:1.25}}],reporter:'list'});
