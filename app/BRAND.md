# BRAND.md — АНГАА АВТО in code

Source of truth: `../handoff/branding/` (BRAND_GUIDE_MN.txt, design-tokens.json, tokens.css, asset-map.json).
No competing mark, icon-library wrench, redrawn wordmark or brand-board hero was created.

## Assets

| Supplied file (handoff/assets) | Installed (public/brand) | Web copies (public/brand/web, `php artisan brand:derivatives`) | Used in |
|---|---|---|---|
| angaa-auto-logo-primary.png (RGBA) | ✓ | -320, -640 | Public header ≥768px (240–260 CSS px), Filament brand logo, print header |
| angaa-auto-logo-reversed-navy.png (opaque navy) | ✓ | -320, -640 | Public footer on navy only (never treated as transparent) |
| angaa-auto-mark.png (RGBA) | ✓ | -16, -32, -48, -96, -180, -192, -512 | Mobile header (48px), mechanic header, favicon (16/32), apple-touch-icon |
| angaa-auto-mascot-welcome.png | ✓ | -240, -480 | Home hero (desktop only), helpful empty states |
| angaa-auto-mascot-success.png | ✓ | -240, -480 | Request-saved receipt only (text says «pending», never «confirmed») |

Derivatives are straight GD resamples of the supplied PNGs with alpha preserved and aspect ratio kept; originals are untouched.
Favicons are raster PNG (no fake SVG). 16/32px legibility should still be checked by eye in each target browser.

## Components

`resources/views/components/`: `brand-logo` (primary / reversed / mark, Mongolian alt «АНГАА АВТО»), `mascot` (decorative, `alt=""`),
`public-header`, `public-footer`, `sticky-mobile-actions`, `service-card`, `price-label`, `status-badge` (text + symbol, never colour alone),
`money` (tabular numerals, integer MNT), `plate-badge`, `timeline`, `estimate-revision`, `empty-state`, `opening-hours`, `demo-banner`,
layouts `public`, `portal`, `mechanic`, `print`.

## Tokens

Exact values from design-tokens.json live in `resources/css/app.css` (`@theme`: ink #142632, primary #0B5A60, hover #08464B,
soft #E7F1F0, canvas #F5F3EE, surface #FFFFFF, muted #58656C, line #D9DFDE, accent #E6AA42 with ink text, success #187047,
danger #B42318; radii 8/12/16/6; card/dialog shadows). Filament panel uses the same hex values via `Color::hex()`.

Typography: Noto Sans self-hosted via `@fontsource/noto-sans` 5.3.0 (OFL; Cyrillic incl. Ө ө Ү ү), bundled by Vite — no font CDN.
Admin uses the same font through `resources/css/fonts.css` + Filament `LocalFontProvider`.

## Rules applied

- Mascot: max one per screen; never in admin tables, calendar, invoices or finance; never implies a diagnosis or confirmed booking.
- Public 16px body, 1.6 line-height; inputs 16px+, 48px controls (mechanic primary actions 48px); reduced-motion respected.
- Board mockups (facade, jacket, card) are not presented as real premises or staff.
