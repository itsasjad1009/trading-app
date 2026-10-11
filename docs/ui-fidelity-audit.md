# Phase 1 reference fidelity audit

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
