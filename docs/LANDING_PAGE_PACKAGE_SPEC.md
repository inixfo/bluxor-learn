# Landing Page Package Spec

Landing pages are same-origin static presentation packages. Production never runs uploaded backend code, install scripts, build scripts, or uploaded JavaScript.

## Package Layout

```text
landing-page.zip
|-- manifest.json
`-- dist/
    |-- index.html
    |-- assets/
    |   `-- styles.css
    |-- images/
    |-- fonts/
    `-- media/
```

## Manifest V2

```json
{
  "schemaVersion": 2,
  "name": "N8N Freelancer Funnel",
  "slug": "n8n-freelancer",
  "version": "1.0.0",
  "author": "Bluxor",
  "sdkVersion": "2",
  "entry": "dist/index.html",
  "description": "Landing page for freelancers",
  "capabilities": ["product", "offers", "checkout", "analytics"]
}
```

## Allowed

- HTML
- CSS, including CSS animations, transitions, variables, gradients, responsive layouts, and pseudo-elements
- local images
- local fonts
- approved static media
- SVG decoration when it contains no script/event handlers/executable URLs
- inline `<style>` and `style` attributes

## Not Allowed

- `.js`, `.mjs`, `.cjs`
- `<script>` tags
- external scripts
- event handler attributes such as `onclick`, `onload`, `onerror`
- `javascript:` or `vbscript:` URLs
- executable CSS URLs
- PHP, Python, shell, binaries, server-side scripts
- nested archives, traversal paths, unsafe path variants, duplicate/case-colliding paths

## Declarative Runtime API

Product bindings:

```html
<h1 data-lbx-product-name></h1>
<p data-lbx-product-short-description></p>
<p data-lbx-product-description></p>
<img data-lbx-product-cover alt="">
<span data-lbx-product-price></span>
<span data-lbx-product-sale-price></span>
<span data-lbx-product-category></span>
```

Offer bindings:

```html
<span data-lbx-offer-name="single"></span>
<span data-lbx-offer-price="single"></span>
<span data-lbx-offer-regular-price="single"></span>
<span data-lbx-offer-saving="single"></span>
```

Checkout:

```html
<button data-lbx-checkout="complete">Get Complete Bundle</button>
```

Interactions:

```html
<button data-lbx-accordion-trigger="faq-1">Question</button>
<div data-lbx-accordion-panel="faq-1">Answer</div>

<button data-lbx-modal-open="preview">Preview</button>
<div data-lbx-modal="preview" hidden>...</div>
<button data-lbx-modal-close="preview">Close</button>
```

Prices remain live through platform context and checkout remains server-authoritative.
