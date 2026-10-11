# Landing animation and spacing update — 11 October 2026

## Changes

Only production files changed in this update:
- `resources/js/components/ParticleNetwork.tsx`
- `resources/css/landing.css`

Added verification: `tests/landing/spacing.spec.ts`. Hero, controls, cards, banner assets, disclaimer wording and backend remain unchanged.

Previous speed: base 8 × multiplier 2 × random factor .5–1 = 8–16 CSS px/s.
New speed: base 16 × multiplier 2.5 × the same random factor = 20–40 CSS px/s.
The new base represents the previous full-speed baseline, so the requested multiplier 2.5 produces a real 2.5× increase rather than merely changing 2 to 2.5 (which would produce only 1.25×).

Slow rotation of velocity preserves its magnitude, with smooth edge bouncing replacing screen wrapping. Existing density, color, opacity, particle radius, spatial-grid drawing, time-based updates, visibility pause, reduced-motion support and effect cleanup remain.

## Browser measurements in CSS pixels

| Width | Features → banners | Banners → disclaimer text | Disclaimer text → footer divider |
|---|---:|---:|---:|
| 1920 | 80 | 48 | 48 |
| 1440 | 80 | 48 | 48 |
| 1024 | 80 | 48 | 48 |
| 768 | 56 | 36 | 36 |
| 390 | 40 | 28 | 28 |

The previous desktop feature gap was 96 CSS px, so the explicit 80 px target reduces that gap. Disclaimer padding increases from 18/20 px to 48/48 px on desktop. Tablet uses 56 px feature separation and 36 px disclosure padding; mobile retains 40 px feature separation and uses 28 px disclosure padding. No empty spacers or artificial wrapper heights.

The deterministic Playwright diagnostic fixes random values at .5 and intercepts actual canvas arc positions over approximately one second. Old seeded velocity is 12 px/s; new theoretical velocity is 30 px/s. The 1440 px run measured 30.29 px/s (2.52×), and the 390 px run measured 30.36 px/s (2.53×). Small timing differences arise because draw-call timestamps are sampled separately from animation timestamps. Measurements verify the applied motion, not just configuration. Gentle curved movement makes net displacement slightly different from total path length. This is a short headless-browser diagnostic rather than physical-device GPU profiling.

## Validation and screenshots

- TypeScript and Vite production build passed.
- Existing frontend tests: 4 passed.
- Requested size/spacing/velocity browser checks: 5 passed.
- Existing landing navigation and animation lifecycle checks at 1440 px: 2 passed.
- No console/page errors or horizontal document overflow in requested viewport checks.
- Reduced motion, resumed animation, mobile resize and unmount on navigation passed.

Updated screenshots and measurements for all five sizes are in `test-artifacts/landing-spacing/{width}/` (hero, feature/banner/disclaimer section, footer, JSON). Those diagnostic hero images use identical seeded particles; the normal visual hero screenshot is `test-artifacts/landing-page-fidelity/after/1440/hero-with-particles.png`.

The existing app remains available at http://127.0.0.1:8000/. No Binance integration or unrelated backend work was performed.
