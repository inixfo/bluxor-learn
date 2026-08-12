# Landing Page SDK

The SDK now describes a declarative package contract, not uploaded JavaScript.

Uploaded packages use `data-lbx-*` attributes. Learn by Bluxor injects the trusted runtime at serve time:

```text
/landing-runtime/lbx-runtime.v2.js
```

The runtime hydrates product data, offer prices, checkout buttons, analytics, and simple UI helpers.

## Browser Runtime

The platform-owned runtime exposes:

```js
window.LearnBluxorRuntime.getContext()
window.LearnBluxorRuntime.track(event, data)
window.LearnBluxorRuntime.formatMoney(amountMinor, currency)
```

Package authors should not call this directly unless debugging locally; production packages should prefer HTML attributes.

## Important Rules

- Do not include custom JavaScript.
- Do not hardcode product prices.
- Do not submit trusted totals.
- Do not implement payment logic.
- Do not call admin/customer APIs.
- Use `data-lbx-checkout="offer_key"` for checkout.
- Use `data-lbx-track="cta_click"` for declarative tracking.
- Keep offer keys stable: `single`, `double`, `complete`.

See `landing-page-sdk/AI_INSTRUCTIONS.md` for agent-facing implementation rules.
