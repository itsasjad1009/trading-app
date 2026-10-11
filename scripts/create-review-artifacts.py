"""Create inspection comparisons and reports from captured browser measurements."""
from pathlib import Path
from PIL import Image, ImageDraw
import json, statistics

root=Path(__file__).resolve().parent.parent
visual=root/'test-artifacts/visual-review'
def load(p): return json.loads(p.read_text(encoding='utf-8-sig'))
sizes=['large','laptop','small-laptop','tablet','mobile']
def comparison(left,right,out,labels,viewport=None):
    images=[]
    for path in [left,right]:
        im=Image.open(path).convert('RGB')
        if viewport: im=im.crop((0,0,min(im.width,viewport[0]),min(im.height,viewport[1])))
        im.thumbnail((960,1200));images.append(im)
    canvas=Image.new('RGB',(1960,max(im.height for im in images)+60),'#202530')
    d=ImageDraw.Draw(canvas)
    for i,(im,label) in enumerate(zip(images,labels)):
        d.text((20+i*980,15),label,fill='white');canvas.paste(im,(20+i*980,45))
    out.parent.mkdir(parents=True,exist_ok=True);canvas.save(out)
for page in ['trade','dashboard','register','kyc','withdraw','profile']:
    comparison(visual/f'before/laptop/{page}.png',visual/f'after/laptop/{page}.png',visual/f'comparisons/{page}-1440-before-after.jpg',['Before at 1440 x 900','After at 1440 x 900'],(1440,900))
refs={'trade':'7f4ad6b5-f05b-40fe-90be-f31556d5ebd9','dashboard':'4fad20c5-12a3-4412-99b7-b5c3783a5a73','register':'4a38f8a8-659f-4818-8126-2d730fe74ae6','kyc':'c40d1ee8-e324-4c3c-bf96-67240d6d3c2e','withdraw':'51235ce1-77b6-410c-bf86-871fcd254ee2','profile':'22b59d34-049c-4d91-9ce1-8a351d10eb5a','home':'c0e828b8-ac4d-4496-8820-ee67604ec751','support':'2fcdfa2e-54ee-4f46-b902-5b2786c79f94','positions':'a889a3f4-013c-41be-9917-420eadaa7932','transactions':'71b5609f-ab39-4b60-91d3-244eca77d05c','deposit':'7a2930c7-5e27-43b9-b6bf-fab25dbf0857'}
for page,ref in refs.items():
    comparison(root/f'reference-screenshots/codex-clipboard-{ref}.png',visual/f'after/large/{page}.png',visual/f'comparisons/{page}-reference-after.jpg',['Supplied Bitop reference (browser chrome retained)','Aster final desktop capture (independent branding/data)'])

before=load(visual/'before/laptop/measurements.json')['pages']['/trade'];after=load(visual/'after/laptop/measurements.json')['pages']['/trade']
rows=[]
for label,key,prop in [('Body font','body','font'),('Page title','title','font'),('Sidebar width','sidebar','width'),('Sidebar item height','nav','height'),('Input height','input','height'),('Input radius','input','radius'),('Card padding','card','padding'),('Chart height','chart','height'),('BUY height','buy','height'),('BUY top position','buy','y'),('Full page height','body','height')]:
    rows.append(f"| {label} | {before[key][prop]} | {after[key][prop]} |")
viewportrows=[];perfrows=[]
for size in sizes:
    data=load(visual/f'after/{size}/measurements.json');m=data['pages']['/trade'];v=m['viewport']
    viewportrows.append(f"| {v['width']}×{v['height']} | {m['chart']['width']:.0f}×{m['chart']['height']:.0f} | {m['buy']['height']:.0f} | {m['buy']['y']:.0f} | {'None' if not any(x['overflow'] for x in data['pages'].values()) else 'Detected'} |")
    perfrows.append(f"| {size} | {data['pages']['/']['ready_ms']} | {data['pages']['/trade']['ready_ms']} | {data['pages']['/trade']['requests']} |")
text='''# Compact UI audit after redesign — 11 October 2026

Final screenshots were captured from the production build and manually inspected through contact sheets and full-size terminal/staff/populated views. All 14 public/customer pages and seven staff module screens were captured at each of five requested sizes. Measurements are reproducible browser geometry, not estimated pixel similarity.

## Measured 1440×900 terminal change

| Element | Before | After |
|---|---|---|
'''+ '\n'.join(rows)+'''

The order ticket sits beside the chart at laptop/desktop widths. At 1440×900 the actions fit in the initial viewport; active positions start immediately beneath the terminal. At narrower desktop/tablet widths the ticket follows the chart; mobile stacks controls and preserves 44px touch inputs/presets. Wide data tables scroll inside their container.

## Final viewport measurements

| Viewport | Chart CSS dimensions | BUY height | BUY top | Customer page overflow |
|---|---|---|---|---|
'''+ '\n'.join(viewportrows)+'''

Shared tokens in resources/css/app.css set 14px body/1.45 line height, 13px labels, 16px card headings, 26px page titles, 28px metrics, 40px desktop inputs and 10px field radius. FormControls supplies shared native Input/Select wrappers. Sidebar is 280px (270px at tablet breakpoint), with 44px links/19px icons. Inter is preferred when installed; Segoe UI/Arial fallback remains local without a remote font download.

Dashboard cards/market rows, positions/transaction tables, deposit QR/request controls, withdrawal authorization, aligned KYC zones, support header/bubbles/composer, profile rows, settings/MFA and authentication cards use the same compact system. Marketing keeps its 88px desktop hero; CTA/features are refined. Existing independent CSS artwork is preserved.

Chart is created once; candle/area visibility changes retain its canvas. One-minute candles are the new unsaved default; stored timeframe preferences are preserved. Volume/axes/crosshair use restrained contrast, auto sizing, UTC labels and an accessible latest OHLC alternative. Simulated feed formula and all accepted/settled execution data are unchanged.

Accessibility checks: visible focus, native label association, alert/status messages, selected preset/timeframe aria-pressed, Radix order confirmation and mobile drawer, Escape/focus return, reduced-motion support, scrollable trade table keyboard target. White SELL text contrast is 4.86:1, selected purple duration 6.02:1; muted body text on cards 7.91:1. Disabled controls are intentionally subdued. This is not an exhaustive WCAG certification.

Staff controls use scoped dark fields and compact Previous/Next controls; repeated attachment queries are consolidated. Filament supplies staff layout and mobile drawer. No supplied staff/mobile screenshot baseline exists.

Comparisons: test-artifacts/visual-review/comparisons. Full captures/measurement files: test-artifacts/visual-review/after. Manual contact sheets: test-artifacts/visual-review/review. No exact pixel-match or automated pixel-regression threshold is claimed.
'''
(root/'docs/ui-audit-after.md').write_text(text,encoding='utf-8')
performance=load(root/'test-artifacts/performance-results.json')['results']
queryrows=[]
for page,runs in performance.items():
    queryrows.append(f"| /{page} | {runs[0]['queries']} | {statistics.median(x['request_ms'] for x in runs):.2f} | {statistics.median(x['sql_ms'] for x in runs):.2f} | {', '.join(str(x['status']) for x in runs)} |")
(root/'docs/performance-review.md').write_text('''# Measured performance review — 11 October 2026

Local measurements below are diagnostics, not production capacity claims. The query probe uses the HTTP kernel, array sessions/cache and the dedicated MySQL 8.4 database with five sequential requests per route. Its first request includes framework warming. These are backend response times without browser/network/render costs. No equivalent pre-change timing capture exists, so no measured speedup percentage is claimed.

| Route | SQL queries/request | Median request ms | Median SQL ms | HTTP statuses |
|---|---|---|---|---|
'''+ '\n'.join(queryrows)+'''

Source improvements: wallet reads use one aggregate without initialization; trade stats use one aggregate rather than separate counters; support history is capped at 50; staff attachments/messages load in page-scoped batches; history indexes added. Trading mutation locking is preserved. Global stats still run for all customer pages, leaving a modest future opportunity for page-specific payload optimization.

Production build: app entry 437.47 kB (142.14 gzip), Platform 97.28 kB (31.00 gzip), lazy MarketChart 179.01 kB (58.08 gzip), CSS 53.94 kB (13.23 gzip). Echo 31.51 kB and Pusher 62.31 kB load dynamically. Vite/TypeScript pass; lazy chart/pages remain split. These figures are build output sizes, not an initial-route total or a before/after reduction.

Browser ready measurements from final screenshot runs:

| Viewport group | Homepage ready ms | Terminal ready ms | Terminal resource entries |
|---|---|---|---|
'''+ '\n'.join(perfrows)+'''

Ready means page navigation plus heading/chart-connected assertion. Each route is visited once per viewport in a local browser session; later pages may use warm HTTP cache. Runs overlap other local QA, so these values are observations, not controlled p95/Lighthouse/network benchmarks. Resource entries include scripts/styles/fetches and should not be interpreted as React render counts.

Chart creation cost reduced structurally: one chart per mounted terminal, view changes only visibility; browser test confirms original canvas remains connected. Fetches have an 8-second timeout, do not overlap, and pause when hidden. Existing candles remain visible with a disconnected status on failure. No profiler trace of chart CPU cost or React render frequency was collected.

Visible customer data refresh remains every 15 seconds because demo quotes are computed on demand; private broadcasts are coalesced and share an in-flight reload guard. Hidden pages pause polling. Chart refresh remains 10 seconds; normal terminal has these two distinct data paths. This is explicit polling overhead, not a claim of a push-only live feed. Reverb import failure has a handled fallback and deployment TLS scheme is respected.

No new heavy animation or remote icon/font dependency. Existing particles respect reduced motion. Future work: controlled slow-network tests, browser CPU/memory profiles, request-specific stats elimination and sustained database load/large-ledger reconciliation benchmarks.
''',encoding='utf-8')
print('Comparison images, measured UI audit and performance report generated.')
