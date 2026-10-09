# autopass landing: references

Owner's design rule: every design is based on references from dribbble.com, figma.com/community, 21st.dev, magnific.com, layers.to, craftwork.design.

## Primary reference (picked by the owner)

| Reference | URL | What is used |
|---|---|---|
| RentalX: Car Rental Website (Behance) | https://www.behance.net/gallery/98940411/RentalX-Car-Rental-Website/modules/571131413 | Overall page structure and rhythm: split hero with a big rounded colour block behind the car, "3 working steps" row with dashed connectors, car-left/benefits-right section, centred "best customer experience" section (top-down car + 6 icon features joined by thin lines), rounded app banner with an overflowing phone, 5-column footer with newsletter. The RIGHT card (owner's favourite) drives the lower half. Removed on purpose: hero search bar, filter chips, category tabs, car listing carousel. |

## Supporting references (approved sources)

| # | Source | Reference | URL | Idea used |
|---|---|---|---|---|
| 1 | Dribbble | Car wash and cleaning landing page | https://dribbble.com/shots/6850763-Car-wash-and-cleaning-landing-page | Car-wash framing of a rental-style layout: short service-focused copy, clean car cut-outs on light background |
| 2 | Dribbble | Car Wash mock website UI | https://dribbble.com/shots/24942849-Car-Wash-mock-website-UI | Simple "how it works" steps + booking-first CTA for a wash service |
| 3 | Dribbble | Rapido: Car Rental Service Landing Page | https://dribbble.com/shots/15962638-Rapido-Car-Rental-Service-Landing-Page | Large white space, soft shadows, small rounded icon tiles |
| 4 | 21st.dev | App Download Section (@ravikatiyar162) | https://21st.dev/@ravikatiyar162/components/app-download-section | App promo block: headline + benefit line + store buttons beside a prominent phone visual |
| 5 | 21st.dev | Download with iPhone (@scrollxui) | https://21st.dev/@scrollxui/components/download-with-iphone | Realistic phone frame showing the real app screen, store badges |
| 6 | 21st.dev | Features 3 (@meschacirung) | https://docs.21st.dev/@meschacirung/components/features-3 | Features linked by thin connector lines around a central visual |

## In-house assets

- Brand kit v1.0 in the repo (`gewash/src/assets/brand`): logo SVGs, Forest `#14482F`, Pass Lime `#B5DD3A`, Plus Jakarta Sans + Noto Sans Georgian (OFL).
- App screen in the phone: approved home screen (`img/app-home-*.png`, from the mock's `img/app-home.png`).

## Photos (Unsplash License: free commercial use, no attribution required)

| File | Used in | Source | Processing |
|---|---|---|---|
| `src-photos/hero-car.png` | Hero (on the Forest block) | Kia EV3 studio shot, "A white suv is on display in a showroom", Unsplash: https://unsplash.com/photos/a-white-suv-is-on-display-in-a-showroom-qz7aNcMgCvw (CDN: https://images.unsplash.com/photo-1719970680701-9c9f5c3ff8b0) | Background removed, trimmed. Served as AVIF/WebP/PNG. |
| `src-photos/benefit-car.png` | Benefits (left) | Hyundai Sonata, "a white car on a white background", Unsplash: https://unsplash.com/photos/a-white-car-on-a-white-background-oa68pmfG-qk (CDN: https://images.unsplash.com/photo-1646960700481-c7be5224a7fc) | Background removed, trimmed. Served as AVIF/WebP/PNG. |
| `src-photos/top-car.png` | Centre, top-down | "An overhead view of an orange sports car" by Erik Mclean (@introspectivedsgn), Unsplash: https://unsplash.com/photos/an-overhead-view-of-an-orange-sports-car-hJrRsawwPns (CDN: https://images.unsplash.com/photo-1739131642757-e44ae0063cb2) | Background removed. Recoloured to brand green in the image file (same matrix as the approved CSS filter: hue-rotate 118deg, saturate 0.62, brightness 0.68, contrast 1.08). Windshield lettering is still covered by `.sunstrip`, the rear spoiler is still faded by `.tailfade`. This photo is a placeholder. |

## Icons

Lucide (ISC license) paths, inlined as SVG symbols. Store badges are drawn in HTML/CSS (Apple logo and Google Play triangles). Favicon and app icons are rasterized from the repo brand SVGs.
