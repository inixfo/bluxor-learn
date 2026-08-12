# AI Agent Instructions

Build a static Learn by Bluxor landing page using the starter in `starter/`.

Rules:

- Do not add backend code.
- Do not add custom JavaScript files or script tags.
- Do not hardcode product prices.
- Use `data-lbx-product-*` attributes for product and offer data.
- Use `data-lbx-checkout="offer_key"` for checkout.
- Use `data-lbx-track="cta_click"` for important CTAs.
- Keep offer keys stable, for example `single`, `double`, `complete`.
- Do not include PHP, Python, shell scripts, binaries, or server files in the package.
- Build locally with `npm run build`.
- Package locally with `npm run package`.
- The ZIP must contain `manifest.json` and `dist/index.html`.

Example prompt:

Create a Bengali sales page for an n8n automation ebook. Audience: freelancers. Use offer keys `single`, `double`, and `complete`. Make `complete` the recommended offer. Do not hardcode prices. Use only declarative `data-lbx-*` attributes for product data, checkout, and analytics.
