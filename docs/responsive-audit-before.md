# Responsive audit before Phase 1B

Inspected React layouts/pages, FormControls, Trades, TradeControls, MarketChart, scoped CSS, Filament operations view and existing Playwright suites. No AGENTS.md was found. Tailwind4 uses CSS-first configuration; no project breakpoint config is present. Existing CSS breakpoints550/850/1050/1199 differed between components.

Fresh nine-size baseline: test-artifacts/responsive-review/before/{viewport}/empty. No page-level overflow was measured in completed baseline captures, but that alone did not prove content usability: the320px terminal price extended beyond its card, timeframes were36px touch targets and tables needed horizontal scrolling to read basic records.

| Page | Issues found |
|---|---|
| Home | Fixed CTA widths squeeze320px; tablet features remain3cols; mobile feature cards unnecessarily tall; legal links have small hit areas |
| Login/register/recovery |52px auth fields too tall for reduced viewport; no keyboard resizing contract; error wrapping and narrow remember/forgot layout need coverage |
| Dashboard | Mobile drawer only below850, no sticky context/balance/profile header; open positions use wide8-column table |
| Trade |360px small-phone chart; pair/price collide at320;36px timeframe targets; tablet sidebar consumes chart width; no mobile trade cards |
| Positions |Wide table for stakes/entry/current/time; filters and pagination need44px controls |
| Transactions |Wide ledger table for fundamental amounts/date; reference inaccessible without horizontal scrolling |
| Deposit |Request history wide table; address copy/QR needs320px containment |
| Withdraw |History table, keyboard-sized viewport, long destination/error containment and percentage controls |
| KYC |Only filenames, no local image preview/status; privacy-conscious URL cleanup needed; upload layout tablet/mobile consistency |
| Support |Fixed50vh message height; composer not tied to visual viewport; narrow input and attachment/send layout |
| Profile |Long fixture emails and account detail values in competing side-by-side columns; referral wrapping |
| Settings/security |Compact inputs need mobile16px text and touch targets; recovery code blocks must remain internally scrollable |
| Filament |Uses supported native menu; custom operations fields40px and narrow actions need scoped touch sizing and labeled table scroll region |

No bottom navigation is planned: a sticky context header plus accessible drawer provides all routes without duplicating controls or obscuring trading/forms/chat. Backend/ledger/settlement/source will remain unchanged. Physical mobile keyboards/pinch gestures cannot be fully established by desktop Chromium; viewport resizing, focus, touch context and chart retention will be tested and limits documented.
