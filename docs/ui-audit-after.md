# Phase 1 UI audit after refinement — 11 October 2026

Fresh evidence: `test-artifacts/visual-review/phase1-after`. Prior compact-phase evidence is preserved in `before` and `after`; this report supersedes its marketing/auth dimensions.

Measured DOM geometry at 1440×900, CSS pixels:

| Page | Title | Input height | First card padding | Chart height | Overflow |
|---|---|---|---|---|---|
| / | 82px | — | 27px | — | No |
| /login | 32px | 52 | 36px | — | No |
| /register | 32px | 52 | 36px | — | No |
| /forgot-password | 32px | 52 | 36px | — | No |
| /dashboard | 26px | — | 16px | — | No |
| /trade | 26px | 40 | 20px | 423 | No |
| /positions | 26px | 40 | 16px | — | No |
| /transactions | 26px | — | 20px | — | No |
| /deposit | 26px | 40 | 20px | — | No |
| /withdraw | 26px | 40 | 20px | — | No |
| /kyc | 26px | 40 | 20px | — | No |
| /support | 26px | 40 | 0px | — | No |
| /profile | 26px | — | 20px | — | No |
| /settings | 26px | 40 | 20px | — | No |

Marketing headline 82px; primary CTA 210×65; secondary 130×65; stats 168×130. Feature grid inner width 1300px, gap28, padding27, icon54/24, heading20/copy15; natural wrapping may increase card height above210. Banners240px. Auth card500px, padding36, radius16, input52px, action60px. Mobile auth input text16px prevents zoom; compact customer forms remain40px desktop/44px mobile.

Terminal remains chart-first: 280px sidebar, 14px body, 40px inputs, 60px BUY/SELL. Auth changes are scoped and do not enlarge customer controls. Desktop ticket sits beside the chart; tablet/mobile stack.

All five requested viewport projects capture fourteen pages and seven staff modules. Drawer Escape/focus-return and no uncaught browser errors are asserted. Reference similarity is structural, not pixel-perfect.

## Five viewport terminal measurements

| Viewport | Chart height | BUY height | Browser exceptions |
|---|---|---|---|
| 1920×1080 | 508px | 60px | 0 |
| 1440×900 | 423px | 60px | 0 |
| 1366×768 | 420px | 60px | 0 |
| 1024×768 | 420px | 60px | 0 |
| 390×844 | 360px | 60px | 0 |

The desktop feature card actually measures414.7×211px (natural line wrapping); banners240px. Mobile feature350×210px and banners220px. Manual review includes all-reference contact sheets, laptop/customer/staff contact sheets, mobile login/home and full-size laptop terminal, plus the five-viewport comparison.
