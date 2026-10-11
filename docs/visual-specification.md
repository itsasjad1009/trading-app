# Reference specification

The 22 supplied screenshots are visual references, not a source of account data or financial behavior. They have been inspected. No pixel match has yet been verified.

## Shared geometry
Screenshots are approximately 1900–1920px wide. Exclude the roughly 100px browser toolbar. Authenticated sidebar is 320px in these captures; use 292px at standard desktop and 320px above 1800px. Sidebar padding 30px; main content padding 30–36px. Cards have 20px radii, 1px muted borders, and 30px padding. Vertical card gaps are about 30px. Navigation rows are 56px high with 20–24px outline icons. Active row is translucent teal with cyan text and border. Main headings are 30–32px, card headings 20–24px, body 16–18px, secondary labels 14–16px. Use a system sans serif stack. Inputs are 56px high with 20px radii. Cyan/purple gradient primary form actions are 64px high. Muted empty-state icons sit above centered gray text.

Theme: background #090D14; sidebar #12151C; card #13171F; secondary #1E222B; input #0B0E14; border #292E38; cyan #00DAE8; purple #A84DF4; muted #9AA7BC; positive #35D68A; negative #F04444; warning amber.

## Screen checklist
| Images | Screen | Reference characteristics | Implementation comparison |
|---|---|---|---|
| 1 | Landing hero | Centered huge two-line headline, gradient second line; particle backdrop; badge; paired actions; four small metric cards | Reviewed; see visual-differences.md |
| 2 | Landing features | 1440px max container; three columns by two rows, icon tiles, left aligned text | Reviewed; see visual-differences.md |
| 3 | Promotions/footer | Two wide banners; original artwork required; separated footer | Reviewed; see visual-differences.md |
| 4–6 | Registration | 560px form card; centered brand/title; six stacked fields; cyan action; sign-in below | Reviewed; see visual-differences.md |
| 7–8 | Dashboard | Duplicate reference; four metric cards; full-width market rows with prices aligned right | Reviewed; see visual-differences.md |
| 9 | Terminal upper | Pair and price header; 400px chart; dark axes; compact timeframe controls; amount presets | Reviewed; see visual-differences.md |
| 10–11 | Terminal lower | Purple duration; two 86px glowing buy/sell buttons; stacked empty position/history cards | Reviewed; see visual-differences.md |
| 12 | Positions | Three centered statistics; open/completed cards | Reviewed; see visual-differences.md |
| 13 | Transactions | One wide centered empty-state card | Reviewed; see visual-differences.md |
| 14 | Deposit | Amber warning; address row/copy; centered white QR tile; metadata | Reviewed; see visual-differences.md |
| 15–16 | Withdrawal | Balance; two method tabs; broad form; percentages; warning; gradient action | Reviewed; see visual-differences.md |
| 17–18 | KYC | Status banner; identity fields; two dashed upload zones; gradient action; benefits grid | Reviewed; see visual-differences.md |
| 19 | Support | Large conversation panel; header, empty message area, bottom composer | Reviewed; see visual-differences.md |
| 20–22 | Profile | Identity card; balance/actions; key-value detail rows; quick links/referral; red logout | Reviewed; see visual-differences.md |

Login, recovery, settings, legal pages, administrative screens, errors, loading and mobile are coherent extensions because no references were supplied for them. Hover is visible on KYC upload and profile withdrawal; focus ring is visible on navigation. Other states must be designed and tested. Mobile uses a keyboard-accessible menu, stacked cards, wrapped presets and chart sized to viewport. Never reuse the reference name, personal data, address, promotional artwork or return claims.

