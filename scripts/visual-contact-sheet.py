from PIL import Image, ImageOps, ImageDraw
from pathlib import Path
import sys
root=Path(sys.argv[1]); out=Path(sys.argv[2]); files=sorted(root.glob('*.png'))
for start in range(0,len(files),8):
 batch=files[start:start+8]; sheet=Image.new('RGB',(1200,((len(batch)+1)//2)*360),'#202530'); draw=ImageDraw.Draw(sheet)
 for idx,path in enumerate(batch):
  im=Image.open(path).convert('RGB'); im.thumbnail((590,325)); x=(idx%2)*600; y=(idx//2)*360
  sheet.paste(im,(x,y+25)); draw.text((x+5,y+5),path.name,fill='white')
 out.mkdir(parents=True,exist_ok=True);sheet.save(out/f'contact-{start//8+1}.jpg')
