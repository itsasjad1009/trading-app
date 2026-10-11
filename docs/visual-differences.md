# Visual review

> Historical initial-implementation comparison. The compact redesign, five-viewport captures and current sidebar/control measurements are documented in visual-differences-after.md and ui-audit-after.md.

Reviewed the 22 reference images and local full-page captures at 1920×1080 and 390×844. This is a manual structural comparison, with browser viewport overflow assertions; no automated pixel similarity score or approved screenshot baseline is claimed. Captures are generated in test-results by the browser suite.

| Reference | Local screen | Comparison and intentional differences |
|---|---|---|
| 1 | Homepage hero | Centered badge, two-line gradient headline, particles, paired buttons and four cards. Original paper-trading copy and truthful demo metrics replace unverified volume/user claims. |
| 2 | Homepage features | Three columns and two rows on desktop; cyan/purple outline icon tiles and charcoal cards. Demo funding/support descriptions replace real financial promises. |
| 3 | Promotions/footer | Two wide cards, original CSS geometric artwork, divided footer. Legal/risk links added; copyrighted promotional imagery omitted. |
| 4–6 | Registration | Centered brand/title, narrow six-field card, rounded inputs, cyan button and sign-in link. Strong 12-character password policy replaces the visible six-character example. |
| 7–8 | Dashboard | Fixed dark sidebar, four summary cards, broad market rows and right-aligned quotes. Demo label, educational score, market movers and quick links added. Account values come from the database. |
| 9 | Terminal upper | Pair/quote header, broad dark chart, timeframes, amount presets and custom input. Supported chart controls replace the proprietary drawing/indicator toolbar. Chart resizing and unintended table styling were corrected during review. |
| 10–11 | Terminal lower | Purple duration selection, prominent cyan BUY/red SELL, active/recent cards. Payout rate, server execution details and real demo positions appear when available. |
| 12 | Positions | Three summary cards plus open/completed sections. Search, filters, pagination and accessible horizontally scrollable data tables extend the empty reference. |
| 13 | Transactions | Large card and centered empty styling. Initial demo funding creates a real transaction, so a new funded account shows history rather than a fabricated empty state. |
| 14 | Deposit | Amber warning, copyable address row, white QR tile and network metadata. Address is unmistakable demonstration text; no copied wallet or real destination. Funding request/history added. |
| 15–16 | Withdrawal | Balance, two methods, rounded fields, percentage controls, warning and gradient submit. Account password/MFA authorize virtual reservations. Bank destination uses a single demo details field. |
| 17–18 | KYC | Status banner, identity fields, two dashed image zones, gradient submit and benefits. Consent/privacy notice added; tax ID optional; truthful process benefits replace withdrawal promises. |
| 19 | Support | Header, large message area, attachment/composer controls. Neutral availability replaces an unverified Online claim; stored timestamps/read states and conversation status appear. |
| 20–22 | Profile | Identity card, wallet actions, details, quick links, referral copy and red logout. Original branding and current customer data replace screenshot identity. |

At standard desktop widths the sidebar is 292px; at the reference's large width it is 320px. Mobile uses a keyboard-accessible drawer and stacked forms/cards. Wide record tables scroll inside their containers. The desktop-only reference has no mobile baseline.

Staff screens use Filament's own layout, with a dark default and cyan accent. They have no supplied visual reference. Staff inputs were given explicit contrast; KYC resubmission/start-review and support closure are functional controls.

Known visual limits: system-font differences, browser rendering, original artwork, additional demo/privacy text, data-dependent rows and a smaller supported chart toolbar prevent an exact pixel match. Automated image-baseline regression and a dedicated keyboard/screen-reader audit remain future QA work.
