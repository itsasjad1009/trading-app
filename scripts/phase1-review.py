from pathlib import Path
import json
from PIL import Image,ImageDraw
root=Path('test-artifacts/visual-review')
out=root/'phase1-comparisons';out.mkdir(exist_ok=True)
for before in (root/'phase1-before/laptop').glob('*.png'):
 after=root/'phase1-after/laptop'/before.name
 if not after.exists():continue
 canvas=Image.new('RGB',(1440,930),'#111722');d=ImageDraw.Draw(canvas)
 for x,path,label in [(0,before,'Before'),(720,after,'Phase 1 after')]:
  im=Image.open(path).convert('RGB').crop((0,0,1440,900));im.thumbnail((720,900));canvas.paste(im,(x,30));d.text((x+12,10),label+' / '+before.stem,fill='white')
 canvas.save(out/(before.stem+'-before-after.jpg'))
b=json.loads((root/'phase1-before/laptop/measurements.json').read_text())['pages']
a=json.loads((root/'phase1-after/laptop/measurements.json').read_text())['pages']
def dim(p,k,prop):
 v=p.get(k);return str(round(v[prop],1)) if v and isinstance(v.get(prop),(int,float)) else (v.get(prop,'—') if v else '—')
rows=[]
for path,p in a.items():
 rows.append(f"| {path} | {dim(p,'title','font')} | {dim(p,'input','height')} | {dim(p,'card','padding')} | {dim(p,'chart','height')} | {'Yes' if p['overflow'] else 'No'} |")
table='| Page | Title | Input height | First card padding | Chart height | Overflow |\n|---|---|---|---|---|---|\n'+'\n'.join(rows)
Path('docs/ui-audit-after.md').write_text('# Phase 1 UI audit after refinement — 11 October 2026\n\nFresh evidence: `test-artifacts/visual-review/phase1-after`. Prior compact-phase evidence is preserved in `before` and `after`; this report supersedes its marketing/auth dimensions.\n\nMeasured DOM geometry at 1440×900, CSS pixels:\n\n'+table+'\n\nMarketing headline 82px; primary CTA 210×65; secondary 130×65; stats 168×130. Feature grid inner width 1300px, gap28, padding27, icon54/24, heading20/copy15; natural wrapping may increase card height above210. Banners240px. Auth card500px, padding36, radius16, input52px, action60px. Mobile auth input text16px prevents zoom; compact customer forms remain40px desktop/44px mobile.\n\nTerminal remains chart-first: 280px sidebar, 14px body, 40px inputs, 60px BUY/SELL. Auth changes are scoped and do not enlarge customer controls. Desktop ticket sits beside the chart; tablet/mobile stack.\n\nAll five requested viewport projects capture fourteen pages and seven staff modules. Drawer Escape/focus-return and no uncaught browser errors are asserted. Reference similarity is structural, not pixel-perfect.\n',encoding='utf-8')
Path('docs/ui-audit-before.md').write_text('# Phase 1 fresh baseline — 11 October 2026\n\nCaptured before changes at five sizes in `test-artifacts/visual-review/phase1-before`. Earlier compact-phase evidence remains untouched. The customer terminal was already compact; reducing it further would contradict the requested chart/control targets.\n\n1440×900 measured baseline: hero88px, login title28px, auth first input'+dim(b['/login'],'input','height')+'px, auth card'+dim(b['/login'],'card','width')+'px / padding'+dim(b['/login'],'card','padding')+', feature card'+dim(b['/'],'card','height')+'px / padding'+dim(b['/'],'card','padding')+', chart'+dim(b['/trade'],'chart','height')+'px, sidebar'+dim(b['/trade'],'sidebar','width')+'px.\n\nMissing login Remember Me, no mail/lock adornments, undersized48px marketing CTAs,40px feature icon tiles,190px simplified banners. Auth and marketing inherited too many compact platform proportions. Baseline had no page-wide overflow in the five projects.\n',encoding='utf-8')
Path('docs/ui-fidelity-audit.md').write_text('''# Phase 1 reference fidelity audit

All 22 source PNGs in `reference-screenshots` were reviewed as contact sheets and against current captures. Source images include differing crops and browser chrome (roughly92–112px where visible); image3 is a content-only crop. Browser zoom and device pixel ratio are unknown. Raster bounds cannot establish original CSS font sizes. We use the supplied target dimensions and actual current DOM measurements, not invented pixel similarity scores. Current Playwright captures use CSS viewport sizes1920×1080,1440×900,1366×768,1024×768,390×844 with no browser toolbar. Side-by-side images preserve those differences; cropped/scrolled reference panels are not treated as full-page baselines.

| Sources | Page | Observed structure and implemented correction |
|---|---|---|
|1|Hero|Centered two-line headline, particle field, paired actions, four stat cards. Headline88→82px; actions48→65px; description max900→780px; stats fixed168×130.|
|2|Features|Six cards in three columns. Grid max inner1300, gap20→28; padding24→27; tile40→54; icon20→24; heading18→20; copy14→15; radius12→17.|
|3|Banners/footer|Two illustrated promotions and divider/footer. Height190→240, original orbit/chart and rules-card SVGs; Aster brand and legal links retained.|
|4–6|Registration|Centered brand/title/card, six labelled fields, cyan action. Card480→500, padding28→36, inputs40→52, action40→60, icon adornments. Strong password requirements preserved.|
|7–8|Dashboard|Sidebar/wallet, four KPIs and market rows. Existing compact280px sidebar/44px navigation,14px body,28px KPI retained; market movers/open positions/quick actions present.|
|9–11|Trading|Chart/pair/timeframes, amount/duration, BUY/SELL and positions/history. Existing423px laptop chart,40px amount input,60px actions retained. Beside-chart ticket is intentional laptop improvement.|
|12|Positions|Three metrics and open/completed cards; compact states/data tables retained.|
|13|Transactions|History card; actual virtual ledger/filter/pagination retained instead of forcing an empty state.|
|14|Deposit|Warning/address/QR metadata; virtual-only destination and funding form retained.|
|15–16|Withdraw|Balance/methods/destination/amount/password action; compact controls, MFA and validation preserved.|
|17–18|KYC|Status/private submission/upload zones/benefits; sample-ID consent and privacy disclosure retained.|
|19|Support|Header/conversation/composer; persisted messages and private attachments preserved.|
|20–22|Profile|Identity/balance/details/shortcuts/referral/logout; dense rows retained.|

No supplied login reference exists among these22 images. Recovery, MFA, settings, staff and mobile reference screenshots are also absent. Those use the explicit Phase1 dimensions and shared visual language, without claiming a reference match.

Marketing, authentication and platform scales are deliberately separate. Paper trading copy, independent SVG artwork, system font fallback, truthful availability, privacy notices, real fixture data and supported Lightweight Charts controls remain intentional differences. Settlement and simulated market source were not modified. See `ui-audit-after.md` for DOM geometry and `phase1-comparisons` for before/after images.
''',encoding='utf-8')
p=Path('docs/visual-differences-after.md');old=p.read_text(encoding='utf-8');old=old[old.index('# Reference comparison after refinement'):];p.write_text('# Phase 1 refinement update\n\nLatest evidence is `test-artifacts/visual-review/phase1-after` and `phase1-comparisons`. Marketing now uses82px hero,65px CTAs,54px feature icons and240px original SVG banners. Authentication now uses500px cards,52px inputs and60px actions, mail/lock icons and working Remember Me. These supersede the older40px auth and simplified CSS-art descriptions below. The compact authenticated layout remains unchanged. Login has no supplied reference; its target dimensions come from the Phase1 prompt. No pixel-perfect claim. See `ui-fidelity-audit.md`.\n\n---\n\n'+old,encoding='utf-8')
