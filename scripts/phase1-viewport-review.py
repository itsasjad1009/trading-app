from pathlib import Path
import json
from PIL import Image,ImageDraw
root=Path('test-artifacts/visual-review/phase1-after')
sheet=Image.new('RGB',(1500,1100),'#202530');d=ImageDraw.Draw(sheet)
for i,v in enumerate(['large','laptop','small-laptop','tablet','mobile']):
 for j,page in enumerate(['home','login','trade']):
  im=Image.open(root/v/f'{page}.png').convert('RGB');im.thumbnail((290,335));sheet.paste(im,(i*300,j*360+25));d.text((i*300+5,j*360+5),f'{v} / {page}',fill='white')
sheet.save('test-artifacts/visual-review/phase1-comparisons/all-viewports.jpg')
for v in root.iterdir():
 if v.is_dir():
  data=json.loads((v/'measurements.json').read_text());p=data['pages']['/'];print(v.name,'feature',p['feature']['width'],p['feature']['height'],'promo',p['promo']['height'],'overflow',any(x['overflow'] for x in data['pages'].values()))
