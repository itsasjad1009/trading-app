import {useEffect,useRef} from 'react';
export const particleConfig={particleCount:110,mobileParticleCount:40,baseVelocity:16,speedMultiplier:2.5,connectionDistance:95,connectionOpacity:.09,particleOpacity:.4,maximumDevicePixelRatio:2};
type Particle={x:number;y:number;vx:number;vy:number;radius:number};
export default function ParticleNetwork(){
 const ref=useRef<HTMLCanvasElement>(null);
 useEffect(()=>{
  const canvas=ref.current!,ctx=canvas.getContext('2d');if(!ctx)return;
  const motion=matchMedia('(prefers-reduced-motion: reduce)');let width=0,height=0,frame=0,last=0,particles:Particle[]=[];
  const draw=()=>{
   ctx.clearRect(0,0,width,height);const cells=new Map<string,Particle[]>(),size=particleConfig.connectionDistance;
   for(const p of particles){const key=`${Math.floor(p.x/size)},${Math.floor(p.y/size)}`;const cell=cells.get(key)||[];cell.push(p);cells.set(key,cell)}
   for(const p of particles){const cx=Math.floor(p.x/size),cy=Math.floor(p.y/size);
    for(let x=cx-1;x<=cx+1;x++)for(let y=cy-1;y<=cy+1;y++)for(const q of cells.get(`${x},${y}`)||[]){if(q.x<=p.x)continue;const distance=Math.hypot(p.x-q.x,p.y-q.y);if(distance<size){ctx.strokeStyle=`rgba(0,218,232,${particleConfig.connectionOpacity*(1-distance/size)})`;ctx.beginPath();ctx.moveTo(p.x,p.y);ctx.lineTo(q.x,q.y);ctx.stroke()}}
    ctx.fillStyle=`rgba(0,218,232,${particleConfig.particleOpacity})`;ctx.beginPath();ctx.arc(p.x,p.y,p.radius,0,Math.PI*2);ctx.fill();
   }
  };
  const tick=(now:number)=>{const dt=last?Math.min((now-last)/1000,.05):0;last=now;const cosine=Math.cos(.05*dt),sine=Math.sin(.05*dt);for(const p of particles){const vx=p.vx*cosine-p.vy*sine;p.vy=p.vx*sine+p.vy*cosine;p.vx=vx;p.x+=p.vx*dt;p.y+=p.vy*dt;if(p.x<p.radius||p.x>width-p.radius){p.vx=-p.vx;p.x=Math.max(p.radius,Math.min(width-p.radius,p.x))}if(p.y<p.radius||p.y>height-p.radius){p.vy=-p.vy;p.y=Math.max(p.radius,Math.min(height-p.radius,p.y))}}draw();frame=requestAnimationFrame(tick)};
  const schedule=()=>{cancelAnimationFrame(frame);frame=0;last=0;if(!document.hidden&&!motion.matches)frame=requestAnimationFrame(tick);else draw()};
  const resize=()=>{const box=canvas.getBoundingClientRect();width=box.width;height=box.height;const dpr=Math.min(devicePixelRatio,particleConfig.maximumDevicePixelRatio);canvas.width=Math.round(width*dpr);canvas.height=Math.round(height*dpr);ctx.setTransform(dpr,0,0,dpr,0,0);particles=Array.from({length:width<640?particleConfig.mobileParticleCount:particleConfig.particleCount},()=>{const angle=Math.random()*Math.PI*2,speed=particleConfig.baseVelocity*particleConfig.speedMultiplier*(.5+Math.random()*.5);return{x:Math.random()*width,y:Math.random()*height,vx:Math.cos(angle)*speed,vy:Math.sin(angle)*speed,radius:.8+Math.random()*1.3}});schedule()};
  const observer=new ResizeObserver(resize);observer.observe(canvas);document.addEventListener('visibilitychange',schedule);motion.addEventListener('change',schedule);
  return()=>{cancelAnimationFrame(frame);observer.disconnect();document.removeEventListener('visibilitychange',schedule);motion.removeEventListener('change',schedule)};
 },[]);
 return <canvas ref={ref} className="particles particle-network" aria-hidden="true"/>;
}


