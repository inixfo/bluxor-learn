# Landing Page Security

Landing pages are native same-origin pages served from:

```text
https://learn.bluxor.com/go/{slug}
```

There is no iframe, wrapper page, landing subdomain, per-page proxy configuration, DNS change, or Docker restart.

## Same-Origin Risk

Because `/go/*` shares origin with `/admin`, `/account`, and `/api`, uploaded packages cannot execute arbitrary JavaScript. Schema V2 packages are HTML/CSS/assets only. Learn by Bluxor injects the trusted runtime.

## CSP

Landing responses use a restrictive CSP:

```text
default-src 'self';
script-src 'self';
style-src 'self' 'unsafe-inline';
img-src 'self' data: https:;
font-src 'self' data:;
media-src 'self' https:;
connect-src 'self';
object-src 'none';
frame-src 'none';
frame-ancestors 'none';
base-uri 'self';
form-action 'self'
```

Inline CSS is allowed for AI design convenience. Inline JavaScript is not allowed.

## Validation

The validator rejects:

- `.js`, `.mjs`, `.cjs`
- script tags and external scripts
- event handler attributes such as `onclick`, `onload`, `onerror`
- `javascript:` and `vbscript:` URLs
- executable CSS URLs
- path traversal, absolute paths, drive-letter paths, null bytes, odd separators, and colon-bearing paths
- duplicate normalized paths and case-colliding paths
- symlink/device or hardlink-like entries where ZIP external attributes expose them
- nested archives
- suspicious compression ratio
- unsupported schema or SDK versions
- missing or malformed `manifest.json`
- missing configured entry point
- blocked executable/server-side extensions
- excessive ZIP size, expanded size, and file count

Validation happens before public serving. Extraction uses a staging directory and swaps it into the public version path only after validation succeeds.

## Runtime

The runtime can call only public landing context, analytics, and central checkout flows. Uploaded authors cannot execute code that calls admin/customer APIs.

Preview uses the same runtime/security model as production and adds `X-Robots-Tag: noindex, nofollow`.
