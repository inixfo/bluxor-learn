# Landing Page Architecture

## Public Model

Public landing pages are native routes on the main domain:

```text
https://learn.bluxor.com/go/{slug}
```

Examples:

- `/go/n8n`
- `/go/n8n-freelancer`
- `/go/bug-bounty`

The old `/lp/{slug}` route redirects to `/go/{slug}` for compatibility.

## Security Model

Because `/go/*`, `/admin`, `/account`, and `/api` share the same browser origin, uploaded packages cannot contain arbitrary JavaScript. Landing Package Schema V2 allows HTML, CSS, images, fonts, SVG, and approved static media. Behavior comes from the trusted first-party runtime:

```text
/landing-runtime/lbx-runtime.v2.js
```

Laravel injects the runtime and safe initial context when serving the package HTML. Package authors use declarative `data-lbx-*` attributes.

## Request Flow

```text
GET /go/n8n-freelancer
  -> Laravel resolves landing page by slug
  -> resolves published version
  -> reads validated dist/index.html
  -> injects safe context and trusted runtime
  -> returns native HTML with strict CSP
```

Assets are resolved generically under `/go/{slug}/{path}`. Publishing a new slug requires no DNS, proxy, Nginx, Docker, or route changes.

## V1 Migration

Schema V1 packages may contain uploaded JavaScript and are not accepted by the current validator. Existing V1 packages should be migrated and re-uploaded as Schema V2 before publishing on `/go/*`.
