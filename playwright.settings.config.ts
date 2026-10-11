import {defineConfig} from '@playwright/test';
export default defineConfig({testDir:'tests/settings',timeout:120000,expect:{timeout:20000},workers:1,use:{baseURL:'http://127.0.0.1:8000'},projects:[[320,640],[390,844],[768,1024],[1024,768],[1440,900],[1920,1080]].map(([width,height])=>({name:String(width),use:{viewport:{width,height}}})),reporter:'list'});
