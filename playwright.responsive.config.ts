import {defineConfig} from '@playwright/test';
const sizes=[[320,640],[375,667],[390,844],[430,932],[768,1024],[1024,768],[1366,768],[1440,900],[1920,1080]];
export default defineConfig({testDir:'tests/responsive',outputDir:'test-artifacts/responsive-test-results',timeout:240000,expect:{timeout:20000},workers:1,use:{baseURL:'http://127.0.0.1:8000',trace:'retain-on-failure'},projects:sizes.map(([width,height])=>({name:`${width}x${height}`,use:{viewport:{width,height}}})),reporter:'list'});
