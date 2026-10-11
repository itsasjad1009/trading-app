# Phase 1B responsive review

The customer shell now switches to a sticky context header and the existing Radix navigation drawer below 1024px. The same navigation configuration serves both layouts. Touch controls use 44px minimum targets; mobile form text is 16px. No bottom navigation was added because it would duplicate the drawer and occupy chart/form/chat space.

## Evidence and scope

Before captures: `test-artifacts/responsive-review/before/{viewport}/empty`. After captures: `test-artifacts/responsive-review/after/{viewport}/{empty|populated|touch}`. Each completed viewport run writes `empty/measurements.json` with page geometry, control heights and browser exceptions. The fixture's initial virtual funding means its initial transactions screen contains the funding entry; “empty” denotes the initial account state, not an empty ledger.

Representative viewports:320×640,375×667,390×844,430×932,768×1024,1024×768,1366×768,1440×900,1920×1080. Boundary checks cover374/375,431,550/551,639/640,767/768,850/851,1023/1024,1199/1200,1279/1280,1439/1440,1535/1536 on home/dashboard/trade.

| Page | Relevant devices | Original issue | Implemented change | Screenshot evidence below after/{viewport} | Status / limitation |
|---|---|---|---|---|---|
| Home | All nine | Narrow fixed CTAs; tablet3-column features | Fluid headline/CTAs,2 mobile statistics columns,1 mobile/2 tablet feature columns, stacked banners/footer | empty/home.png | Nine-size matrix passed |
| Login/register/recovery | All nine | Narrow options and oversized fields |46px mobile fields,16px input text,44px disclosure/options, wrapped validation | empty/login.png,register.png,register-validation.png,forgot-password.png | Nine-size matrix passed; physical keyboard unverified |
| Dashboard | All nine | Sidebar consuming tablet space; wide positions table | Sticky header below1024,2 KPI columns, compact markets/quick actions, position cards | empty/dashboard.png,populated/dashboard-active.png | Nine-size matrix passed |
| Trade | All nine |320px quote overflow,36px toolbar, desktop records | Flexible complete quote,44px toolbar/presets,280/320/400px chart,56px mobile directions, cards | empty/trade.png,populated/trade-active.png | Nine-size matrix passed; native pinch unverified |
| Positions | All nine | Wide records | Cards below1024, tables above; stable countdown, complete entry/current/stake/expiry/P&L | empty/positions.png,populated/positions-active.png,positions-completed.png | Nine-size matrix passed |
| Transactions | All nine | Basic values require horizontal scrolling | Cards with available/reserved amounts, time/status/reference details; desktop table retained | empty/transactions.png,populated/transactions-active.png | Nine-size matrix passed |
| Deposit | All nine | Request history and long address containment | Mobile request cards, contained sample address/QR and44px copy/submit | empty/deposit.png,populated/deposit.png | Nine-size matrix passed; demo requests only |
| Withdraw | All nine | Long destination and short preset targets | Full-width mobile fields, decimal keyboard hint, wrapped44px percentages/history cards | empty/withdraw.png; existing browser workflow screenshots | Nine-size layout passed; desktop/mobile workflows passed |
| KYC | All nine | Filename-only uploads | Single mobile/two tablet columns; local blob preview/status; revoke URL on replace/unmount | empty/kyc.png,populated/kyc-local-preview.png | Nine-size matrix passed; private submission passed on desktop/mobile |
| Support | All nine | Fixed message height and squeezed composer | VisualViewport-aware flex panel, safe-area composer, contained scrolling, readable bubbles | empty/support.png,populated/support.png,support-reduced-viewport.png | Nine-size matrix passed; reduced viewport approximates keyboard |
| Profile | All nine | Competing long values | Stacked mobile details, wrapped identifiers/referrals, compact shortcuts | empty/profile.png | Nine-size matrix passed |
| Settings/security | All nine | Compact controls unsuitable for touch |16px mobile input text,44px targets, contained recovery data | empty/settings.png | Nine-size matrix passed; complete authenticator enrollment outside browser coverage |
| Filament operations | All nine | Custom fields/actions too small | Native Filament navigation retained; scoped44px fields/actions; labeled internal table scrolling | empty/staff-{users,kyc,deposits,withdrawals,support,trades,transactions}.png | Nine-size matrix passed; bodies masked to protect existing records |

## Completed responsive verification

The main matrix passed 9 viewport runs plus 1 boundary run (21 widths; 8 project-specific skips). It captured 126 measured public/customer views and 63 masked staff views, plus validation and populated states. A separate protected-auth/legal run passed with 63 additional captures across all nine sizes. The 320px touch test passed focus trapping, Escape/focus return, scroll locking, navigation close, pair/timeframe/amount/duration controls, large-order cancellation, trade placement and a Fortify remember cookie. Phone controls measured at least 44px high; no global zoom or transform was used.

Before/after comparisons and nine-size contact sheets are in `test-artifacts/responsive-review/comparisons`. Visual inspection checked the mobile quote and chart, responsive records, tablet layout, desktop terminal, marketing/auth/KYC/support grids and protected-auth pages. Native gesture/keyboard behavior remains the physical-device limit described below.

## Implementation and performance

Chart creation remains a single mount effect with cleanup; automatic sizing retains the same canvas across pair/timeframe/view/orientation changes. Horizontal chart touch drag and pinch are enabled, while vertical touch drag is left for page scrolling. Echo subscriptions and existing cleanup/poll behavior remain unchanged. Particle count is reduced on small phones and reduced-motion disables animation. Existing lazy page/chart chunks and vector banner artwork are retained.

Production build passed: CSS67.32kB (15.67gzip), Platform100.99kB (31.75gzip), chart179.08kB (58.09gzip), shared app437.47kB (142.13gzip). These are bundle sizes, not a physical-device performance benchmark. No global zoom/transform scales the application.

## Final regression results

- Backend: 43 tests passed, 206 assertions (isolated in-memory SQLite).
- Frontend: 4 Vitest tests passed.
- TypeScript and Vite production build passed.
- Responsive matrix: 10 passed in 12.9 minutes (nine sizes plus boundary run; eight intentional project skips).
- Protected-auth/legal: 1 passed in 1.1 minutes, exercising seven layouts across nine sizes.
- Touch: 1 small-phone interaction test passed.
- Existing browser workflows: desktop 2 passed; final mobile rerun 2 passed in 3.0 minutes. Registration, remembered login, password toggle, chart controls, order cancellation/placement, support, funding, password-authorized withdrawal, KYC front/back submission, profile save and staff response all passed.
- Ledger reconciliation: unbalanced, empty transactions, negative wallet accounts, reservation mismatches all zero. No failed queue jobs.

After evidence currently includes 363 PNGs; baseline includes 126 PNGs. Generated comparisons/contact sheets total 94 JPGs. Some workflow images are additional snapshots outside the measured matrix. Staff bodies are masked; fixture data is synthetic. No destructive data cleanup was performed.

Intermediate failures were test timing/selectors and environment pressure: chart captures now await connected data, mobile stake assertions include USDT, sensitive requests observe cooldowns, and Filament menu tests close using its visible backdrop rather than a covered top-bar control. Final runs above passed without changing production throttles or forcing clicks.

## Verification limits

Desktop Chromium touch emulation, viewport resizing and focus checks cannot prove native iOS/Android keyboard, safe-area, hardware pinch or low-end-device performance. Physical device review remains recommended. Shared auth components supply verification/password-confirmation/reset/MFA layouts; verification, password confirmation, reset-password layout and MFA challenge are additionally captured at all nine sizes under auth/. MFA is enabled only on a new local fixture; complete browser authenticator enrollment is not exercised. Existing backend authentication/MFA tests provide functional coverage.

No backend trading, settlement, ledger, market-source, authorization or database reset changes were made. Test accounts use example.test addresses, sample PNGs and virtual balances. Staff screenshots mask existing table bodies. Intermediate parallel browser overload and sensitive-operation429 responses were addressed by sequential suite scheduling and a61-second test cooldown; production rate limits were preserved.



