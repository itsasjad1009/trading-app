from pathlib import Path
from PIL import Image, ImageDraw
import json

root = Path('test-artifacts/responsive-review')
sizes = ['320x640','375x667','390x844','430x932','768x1024','1024x768','1366x768','1440x900','1920x1080']
out = root / 'comparisons'
out.mkdir(parents=True, exist_ok=True)
summary = {}
for viewport in sizes:
    final = root / 'after' / viewport
    data = json.loads((final / 'empty' / 'measurements.json').read_text())
    summary[viewport] = {'pages': len(data['pages']), 'overflow': any(p['overflow'] for p in data['pages'].values()), 'browserErrors': data['browserErrors'], 'chart': data['pages']['/trade']['chart'], 'input': data['pages']['/login']['input']}
    for state in ['empty','populated']:
        files = sorted((final / state).glob('*.png'))
        for start in range(0, len(files), 8):
            batch = files[start:start+8]
            sheet = Image.new('RGB',(1200,((len(batch)+1)//2)*380),'#202530')
            draw = ImageDraw.Draw(sheet)
            for i, path in enumerate(batch):
                picture = Image.open(path).convert('RGB')
                picture.thumbnail((585,345))
                x,y=(i%2)*600,(i//2)*380
                sheet.paste(picture,(x,y+28))
                draw.text((x+5,y+5),f'{viewport} / {state} / {path.name}',fill='white')
            sheet.save(out / f'{viewport}-{state}-{start//8+1}.jpg')
    for page in ['home','login','trade','positions','support']:
        sheet = Image.new('RGB',(1200,1000),'#202530')
        draw = ImageDraw.Draw(sheet)
        for i, phase in enumerate(['before','after']):
            picture = Image.open(root / phase / viewport / 'empty' / f'{page}.png').convert('RGB')
            picture.thumbnail((590,960))
            sheet.paste(picture,(i*600,30))
            draw.text((i*600+5,5),f'{phase} / {viewport} / {page}',fill='white')
        sheet.save(out / f'{viewport}-{page}-before-after.jpg')
(out / 'measurements-summary.json').write_text(json.dumps(summary,indent=2))
for page in ['home','login','trade','dashboard','kyc','support']:
    sheet = Image.new('RGB',(1500,1800),'#202530')
    draw = ImageDraw.Draw(sheet)
    for i, viewport in enumerate(sizes):
        width,height = map(int,viewport.split('x'))
        picture = Image.open(root / 'after' / viewport / 'empty' / f'{page}.png').convert('RGB')
        picture = picture.crop((0,0,width,min(height,picture.height)))
        picture.thumbnail((490,565))
        x,y=(i%3)*500,(i//3)*600
        sheet.paste(picture,(x,y+25))
        draw.text((x+5,y+5),f'{viewport} / {page}',fill='white')
    sheet.save(out / f'all-sizes-{page}.jpg')
print(json.dumps(summary,indent=2))
