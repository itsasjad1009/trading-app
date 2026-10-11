from pathlib import Path
import json
root=Path('test-artifacts/visual-review/phase1-after');lines=[]
for name in ['large','laptop','small-laptop','tablet','mobile']:
 d=json.loads((root/name/'measurements.json').read_text());p=d['pages']['/trade'];v=p['viewport'];lines.append(f"| {v['width']}×{v['height']} | {p['chart']['height']:.0f}px | {p['buy']['height']:.0f}px | {len(d['browserErrors'])} |")
p=Path('docs/ui-audit-after.md');s=p.read_text(encoding='utf-8').split('\n## Five viewport terminal measurements')[0]+'\n## Five viewport terminal measurements\n\n| Viewport | Chart height | BUY height | Browser exceptions |\n|---|---|---|---|\n'+'\n'.join(lines)+'\n\nThe desktop feature card actually measures414.7×211px (natural line wrapping); banners240px. Mobile feature350×210px and banners220px. Manual review includes all-reference contact sheets, laptop/customer/staff contact sheets, mobile login/home and full-size laptop terminal, plus the five-viewport comparison.\n';p.write_text(s,encoding='utf-8')
