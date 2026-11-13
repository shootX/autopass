---
name: awesome-design-md
description: Ready-made DESIGN.md design systems of well-known brands (Stripe, Notion, Linear, Vercel, Apple, Airbnb, Revolut, Wise and ~80 more) from VoltAgent/awesome-design-md. Use when the user asks for a page, screen or restyle "in the style of" a brand, wants a DESIGN.md for the project, or wants to pick a visual direction from known design systems.
argument-hint: "[brand, e.g. stripe | notion | linear.app] [what to build or restyle]"
user-invocable: true
license: MIT
---

# Awesome DESIGN.md

`<skill>` is this folder. `<skill>/design-md/<brand>/DESIGN.md` holds one brand's design system
(colors, typography, spacing, components, do's and don'ts) in the Google Stitch DESIGN.md format.
Source: https://github.com/VoltAgent/awesome-design-md (MIT, see `LICENSE`).

## How to use

1. List the brands: `ls <skill>/design-md`. Match the user's words to a folder name
   (for example "Linear" is `linear.app`, "Mistral" is `mistral.ai`).
2. Read `<skill>/design-md/<brand>/DESIGN.md` in full before designing anything.
3. If the user only wants to compare directions, summarize two or three candidates
   (palette, type, mood) and let them choose.
4. To adopt a style for the project, copy the file to the project's root `DESIGN.md`
   (or the web app's root) and adapt it: keep the structure, replace brand names and logos,
   and keep the project's own brand colors when the user asks for that.
   Never copy a brand's logo, trademark or exact copy text into the product.
5. Build or restyle the requested UI following that DESIGN.md, then verify it renders
   (the `playwright-cli` skill can open a local dev server and take screenshots).

Works together with the `impeccable` skill (which reads `DESIGN.md`) and the
`design-taste-frontend` skill.
