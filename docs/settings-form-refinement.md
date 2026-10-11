# Settings form refinement — 11 October 2026

## Implementation

Production files changed:

- `resources/js/components/FormSection.tsx`: reusable field label/input grouping and heading/status/description sections.
- `resources/js/components/SecurityPanel.tsx`: two-factor section with password/code fields, grouped actions, readable disabled states, busy labels and separated recovery codes. Existing request endpoints, password checks, enrollment, confirmation and disable handlers remain.
- `resources/js/pages/Platform.tsx`: grouped profile form and separate password recovery section. Profile PATCH validation/business logic remains on the server.
- `resources/css/app.css`: scoped Settings spacing, button dimensions and mobile layout. Shared customer outline buttons no longer receive Tailwind's unintended default outline; visible keyboard focus remains.

Account Security now contains a two-factor heading/status, explanatory text, password field and action, followed by a divider and distinct Password Recovery section. Profile fields use a consistent grid. Cards use 24 px desktop / 16 px phone padding, labels have 6 px input separation, related profile fields have 20 px gaps, and subsection divider has 24 px space on each side.

The recovery navigation check uncovered an existing defect: signed-in users visiting `/forgot-password` are redirected by Fortify's guest middleware to the dashboard. Reset Password now uses the existing POST `/logout` route, then visits `/forgot-password` after success. Its description explicitly explains signing out. No Fortify route, reset-token handling, password verification, MFA rule, recovery-code handling, schema or financial logic changed.

## Measured Settings geometry

| Width | Password input → 2FA action | Recovery description → action | Action heights | Divider top padding | Profile field gap |
|---|---:|---:|---|---:|---:|
| 320 | 16 px | 16 px | at least 44 px; wraps as needed | 24 px | 20 px |
| 390 | 16 px | 16 px | 44 px | 24 px | 20 px |
| 768 | 16 px | 16 px | 44 px | 24 px | 20 px |
| 1024 | 16 px | 16 px | 42 px | 24 px | 20 px |
| 1440 | 16 px | 16 px | 42 px | 24 px | 20 px |
| 1920 | 16 px | 16 px | 42 px | 24 px | 20 px |

At phone widths, buttons are full width and use automatic height to avoid clipping wrapped labels. Disabled enrollment remains disabled until a password is provided. Busy actions disable repeat submissions and announce processing through labels and `aria-busy`. Error text wraps in the form flow and does not overlay adjacent controls.

## Customer form audit

Registration, login, deposit, withdrawal, KYC, profile and Settings were inspected in source and captured in the browser at all six sizes. Existing registration/login label spacing, direct form action margins, KYC notices and withdrawal grouping already separate their controls; the reported crowding was specific to security controls rendered outside a form and shared spacing structure. No unrelated redesign was needed.

Deposit actions have approximately 22 px separation from their preceding field-label box, KYC actions 16 px from preceding feedback/help, and withdrawal actions have separation after the notice (32 px including its existing margin). Those existing forms remain unchanged. Profile display actions are separate from editable Settings controls. The shared outline/focus correction applies to customer actions, not the homepage or authentication typography.

## Validation

- TypeScript and Vite production build: passed after final changes.
- Existing frontend suite: 4 passed.
- Isolated Laravel suite: 43 passed / 206 assertions, including authentication, password confirmation, reset notification, MFA authorization and recovery-code behavior.
- Settings Playwright workflow: all six viewport projects passed. Final follow-up captures wait for each actual page heading before measuring, avoiding measurements during React initialization.
- Tested profile server validation and successful name/phone update, disabled enrollment, wrong password rejection, real enrollment/QR response, authenticator confirmation, enabled status, disable flow and password-reset navigation.
- Tested profile and MFA loading states using short network delays; existing server responses remain real.
- Keyboard Tab skips the disabled MFA action and reaches Reset Password.
- Mobile validation error screenshot at 320 px; no document overflow.
- No unexpected console/page errors. Expected HTTP 422 console entries from intentional validation failures are excluded; their visible error messages are asserted.

Tests create only new `example.test` fixture accounts. MFA OTP generation reads only the fixture account inside the test runner; secrets and recovery codes are not printed. Enabled-state screenshots mask recovery codes. No existing user's account or settings are modified.

## Evidence

`test-artifacts/settings-refinement/{320,390,768,1024,1440,1920}/` contains screenshots of Settings and audited pages plus measured CSS geometry. The 1440 directory includes profile-validation, incorrect-password and enabled-MFA screenshots; the 320 directory includes wrapped validation feedback.

Preview files:

- `test-artifacts/settings-refinement/1440/settings.png`
- `test-artifacts/settings-refinement/390/settings.png`

Test sources: `tests/settings/settings.spec.ts` and `playwright.settings.config.ts`.

These are Chromium viewport checks; physical-device keyboard/screen-reader testing was not performed. The application remains at http://127.0.0.1:8000/settings.
