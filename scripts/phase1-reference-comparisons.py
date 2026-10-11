from pathlib import Path
from PIL import Image,ImageDraw
root=Path('test-artifacts/visual-review');out=root/'phase1-comparisons'
refs={'home':['c0e828b8','f37f2465','60757d24'],'register':['4a38f8a8','c417532d','80792c6f'],'dashboard':['4fad20c5','4ea92f02'],'trade':['7f4ad6b5','1868f4f4','58ed88ab'],'positions':['a889a3f4'],'transactions':['71b5609f'],'deposit':['7a2930c7'],'withdraw':['51235ce1','14094d34'],'kyc':['c40d1ee8','61a8e34f'],'support':['2fcdfa2e'],'profile':['22b59d34','d4c934f3','e38c48a1']}
for page,prefixes in refs.items():
 for prefix in prefixes:
  ref=next(Path('reference-screenshots').glob(f'codex-clipboard-{prefix}*.png'));after=root/'phase1-after/large'/f'{page}.png';ims=[]
  for p in [ref,after]:
   im=Image.open(p).convert('RGB');im.thumbnail((960,1300));ims.append(im)
  sheet=Image.new('RGB',(1960,max(im.height for im in ims)+50),'#202530');d=ImageDraw.Draw(sheet)
  for i,im in enumerate(ims):sheet.paste(im,(20+i*980,45))
  d.text((20,12),f'Reference {prefix} / chrome and scroll crop retained',fill='white');d.text((1000,12),f'Phase1 {page} / 1920 CSS viewport, full page',fill='white');sheet.save(out/f'{page}-{prefix}-reference-after.jpg')
