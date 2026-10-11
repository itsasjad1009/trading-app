import {defineConfig} from '@playwright/test';
export default defineConfig({testDir:'tests/market-browser',timeout:120000,expect:{timeout:20000},workers:1,use:{baseURL:'http://127.0.0.1:8000'},projects:[{name:'1440',use:{viewport:{width:1440,height:900}}},{name:'390',use:{viewport:{width:390,height:844}}}],reporter:'list'});
