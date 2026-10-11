# Aster landing-page visual fidelity — 11 October 2026

## Scope and outcome

Landing page only. No Laravel business logic, authentication rules, schema, wallet, settlement, trading engine, KYC, or admin changes. Aster branding and truthful demo descriptions remain. The page uses original generated artwork rather than copied Bitop graphics. This is a close geometry match, not a claim of pixel-perfect identity. Visual approval is the next checkpoint before any separate market-data work.

## Reference identification and calibration

The six supplied files are preserved as `reference-screenshots/landing/1.png` through `6.png`. Identification follows image content; the pasted prompt's ordering differs from the actual attachments.

| File | Content | Screenshot | Toolbar excluded | Website area |
|---|---|---|---|---|
| 1 | Bitop hero | 1886×978 | top 69 px | 1886×909 |
| 2 | Bitop features | 1917×940 | top 60 px, right 17 px scrollbar | 1900×880 |
| 3 | Bitop banners/footer | 1896×540 | none | 1896×540 |
| 4 | Previous Aster hero | 1917×960 | top 60 px | 1917×900 |
| 5 | Previous Aster features | 1917×963 | top 60 px | 1917×903 |
| 6 | Previous Aster banners/footer | 1917×953 | top 60 px | 1917×893 |

The supplied Aster screenshots render existing CSS dimensions at approximately 1.25×: the 210 px CTA appears 262 px wide, 168 px statistics cards appear 210 px wide, and the 1300 px feature grid appears 1625 px wide. This consistently supports an **apparent 125% display scale**, but static PNGs cannot distinguish browser zoom, OS scaling, or subsequent image processing. No original browser metadata is available, so identical original zoom/DPR cannot be proven.

Before editing, Playwright captured 13 baseline sizes, including exact 1886×909 and 1900×880 CSS viewports at DPR 1, and a calibrated 1509×727 CSS viewport at DPR 1.25 (1886×909 screenshot viewport). Fonts were awaited with `document.fonts.ready`; their status is recorded as loaded. The final capture repeats those settings. Production uses ordinary CSS measurements; no CSS zoom, scale, or transform-based resizing is used.

The calibrated comparison normalizes reference geometry by 1.25. Its full hero element is 1886×910 due to rounding of the 728 px section height. For the 909 px overlay, one bottom row is cropped. Features and banners compare component crops directly. Footer widths differ by 10 px and are center-aligned without scaling. Scroll offsets of the supplied feature/banner screenshots are unknown, so full document Y positions cannot be recovered; local section/card geometry is compared separately.

## Measured geometry

All figures below are **rendered image pixels at the inferred 1.25 scale**, not CSS pixels. Reference edges are measured from PNGs and have about ±1 px rounding uncertainty. Before/after values derive from browser bounding rectangles.

| Element | Reference | Before | After | After deviation |
|---|---|---|---|---|
| Primary CTA | 231×75 | 262.5×81.25 | 231.25×75 | +0.25×0 |
| Secondary CTA | 144×75 | 162.5×81.25 | 143.75×75 | −0.25×0 |
| Statistics card | 187×148 | 210×162.5 | 187.5×147.5 | +0.5×−0.5 |
| Feature card | 460×236 | 518.32×263.75 | 460×236.25 | 0×+0.25 |
| Feature icon tile | 60×60 | 67.5×67.5 | 60×60 | 0×0 |
| Banner | 705×240 | 795×300 | 705×240 | 0×0 |
| Footer height | 135 | 128.36 | 135 | 0 |

Feature and banner content width is now 1152 CSS pixels (1440 rendered), with 24 CSS pixel gaps (30 rendered). Feature padding is 24 CSS pixels (30 rendered). Cards use 18 px titles / 14 px body copy; hero uses 72 px title / 20 px description / 60 px action height. At 1440×900 and normal DPR 1 these remain compact rather than growing to fill the display.

Typography is measured separately with a white-pixel threshold in the isolated first headline region (`measure_ink.py`). The threshold excludes muted copy and the colored second line; it measures visible ink rather than the full-width H1 box:

| First-line ink | Reference | Before | After |
|---|---|---|---|
| Width | 394 px | 428 px | 393 px |
| Height | 68 px | 77 px | 66 px |
| Top, excluding toolbar | 199 px | 193 px | 204 px |

The remaining headline top difference is +5 px and height difference −2 px. The CTA top is 517.5 versus about 514 px (+3.5); statistics top is 672.5 versus about 669 px (+3.5). Announcement is 328.69×47.5 versus approximately 335×47; its top is 97.5 versus 97 px. The reference content appears centered about 7.5 px to the right of the supplied hero image's center; Aster remains centered in its actual viewport. This offset is documented rather than introduced as a fragile hard-coded translation.

The reference's exact font family and browser rasterization cannot be determined from PNGs. Aster's homepage explicitly uses Arial/Helvetica/system sans-serif, weight 700 for the hero, 600 for feature titles. Copy remains two lines at the calibrated desktop width. Differences in words mean a whole-page pixel-diff score would be misleading.

## Original feature specification and public demo copy

Original structure/order is recorded here for future visual audits:

1. **Live Trading** — Real-time candlestick charts with 10+ crypto pairs and instant trade execution.
2. **Options Trading** — Crypto options with up to 230% profit potential on durations from 60s to 360s.
3. **KYC Verification** — Secure identity verification using your country's official identification documents.
4. **Easy Deposits** — Deposit via USDT TRC20 with QR code support and fast processing.
5. **Bank Withdrawals** — Withdraw to your preferred crypto wallet or bank account worldwide.
6. **24/7 Support** — Our dedicated team is here to assist you with any trading needs around the clock.

These are reference descriptions, **not Aster service claims**. Public Aster cards retain simulated charts, directional demo positions, private document submission, virtual funding, demo withdrawal review, and a support center. Unsupported uptime, user count, real volume, custody, competition, and payout claims are absent. Public metrics remain 6 demo pairs, 100% virtual funds, 60–360s expiry choices, and Demo/no real custody. No public fixture mode was needed.

## Artwork

Generated with the built-in imagegen tool using the imagegen skill; no CLI fallback. Original source PNGs remain in the Codex generated-images directory. Production WebP files are original illustrations, 1440×480, compressed at quality 86:

- `public/images/landing/practice-arena.webp` — 143,412 bytes.
- `public/images/landing/aster-demo-card.webp` — 112,554 bytes.

Both images use full-bleed cover cropping, lazy loading, explicit dimensions, dark left foreground gradients, and HTML text/links. The first contains layered PRACTICE/TRADING typography, neon charts and technical scenery; the second contains a detailed Aster demo card with cyan circuitry. Artwork is intentionally original and differs from Bitop composition/details.

Generation prompts:

> Use case: ads-marketing. Create an ORIGINAL Aster paper-trading website banner background, wide 3:1 landscape. Highly detailed futuristic cyberpunk trading practice arena, deep black-purple, luminous cyan and magenta circuitry, neon angular geometry, tiny technical glyphs, layered oversized background typography reading only 'PRACTICE' and 'TRADING' with dramatic perspective, sophisticated polished sci-fi campaign rendering. Reserve leftmost 35 percent as dark low-detail negative space for separate HTML heading/CTA. Rich illuminated typography and circuitry concentrated center/right. No foreground copy, no event or competition claims, no dates, no Bitop or other company branding, no financial performance promises. Original illustration, not a screenshot, full bleed opaque background.

> Use case: ads-marketing. ORIGINAL premium futuristic fintech illustration for Aster paper-trading site, wide 3:1 full-bleed banner. Deep almost-black navy background with luminous cyan fine circuitry, tiny volumetric particles, holographic technical fragments and perspective grid. Detailed premium translucent metal/glass card object at center-right, tilted in 3D, etched chip, fine technical security textures, subtle abstract four-point star symbol, chart details, elegant cyan rim light. Leftmost 35 percent dark negative space for HTML heading and CTA. Polished photorealistic sci-fi render with comparable rich detail to premium cyberpunk fintech campaign art. No payment-brand logos, no bank promises, no cryptocurrency claims, no foreground text, no Bitop artwork. Text on card only 'ASTER' and 'DEMO'. Opaque background.

## Animation and performance

`ParticleNetwork.tsx` mounts one effect/animation loop. Centralized parameters: 110 desktop particles, 40 mobile particles, base velocity 8 CSS px/s, speed multiplier 2, connection distance 95 px, connection opacity .09, particle opacity .4, DPR cap 2. Individual velocities vary from 8–16 px/s. Motion uses delta seconds, capped at 50 ms after interruptions. A spatial grid limits connection checks to neighboring cells rather than testing every pair.

ResizeObserver rescales the canvas and recreates its particle population only on canvas resizing. Visibility changes cancel/restart scheduling; reduced motion draws a static network. Cleanup cancels requestAnimationFrame, disconnects the observer and removes both listeners. React renders do not recreate the effect. Canvas drawing performs no per-frame DOM layout reads.

The old CSS float effect had a different path/duration model; a precise velocity ratio to that effect or the original Bitop animation cannot be recovered from static screenshots. The new configurable 2× multiplier is a balanced approximation, not a verified match to Bitop motion. The browser test proves static reduced-motion rendering, resumed movement, mobile resizing and canvas removal on navigation. A 1.017-second local headless sample recorded 62 callbacks, approximately 60.0 scheduled FPS (`animation-cadence.json`). Animation cadence is recorded separately; it is a local headless scheduling sample, not a device-wide GPU or memory guarantee.

## Validation

- TypeScript `tsc --noEmit` and Vite production build: passed after final source changes.
- Existing Vitest frontend suite: 4 passed.
- Laravel isolated regression suite: 43 passed / 206 assertions. No real database mutation required.
- Baseline landing captures: 13 passed.
- Final landing browser suite: 14 passed; 12 intentional skips (animation lifecycle is run once at 1440, geometry/navigation at every size).
- Widths tested: 320, 375, 390, 430, 768, 1024, 1366, 1440, 1728, 1920, plus reference-sized 1886 and 1900 and calibrated DPR 1.25.
- No horizontal document overflow or browser console/page errors in these checks.
- Get Started navigates to registration; Sign In navigates to login. Promotional and legal links retain their existing destinations.
- Desktop, mobile, tablet, reduced-motion, original-image loading and responsive card/banner screenshots inspected.

No physical-device touch/GPU profiling or long-duration heap test was performed. This is a landing-only verification, not another full authenticated workflow audit; the existing backend suite and untouched platform scope provide the regression coverage appropriate to this change.

## Evidence and visual approval

All screenshots and raw browser measurements: `test-artifacts/landing-page-fidelity/before/` and `after/`. Particles are hidden only in static comparisons; `hero-with-particles.png` shows the static reduced-motion network. The live app animates unless reduced motion is requested.

`comparisons/` contains reference/before/after panels, 50%-alpha overlays, geometry JSON, and headline ink measurements. Images are centered/cropped without scaling the UI for geometry comparisons. Dynamic particle positions, original artwork, and deliberately different copy are excluded from numerical pixel-diff scoring; no misleading similarity percentage is reported.

Visual approval previews:

- `after/calibrated125/hero-with-particles.png`
- `after/calibrated125/features.png`
- `after/calibrated125/banners.png`
- `after/calibrated125/footer.png`
- `after/1440/full.png`
- `after/390/full.png`

The running page is http://127.0.0.1:8000/. Review these previews before authorizing work on any other module.
