# Shopify webhook security note

Project 25 uses n8n's built-in Shopify Trigger for the main paid-order path. In the current n8n source, the trigger checks Shopify headers, computes HMAC-SHA256 over the raw request body using the configured app secret, compares the signature, and verifies the webhook topic before returning workflow data.

If you replace the Shopify Trigger with a generic Webhook node, you own HMAC verification. Shopify requires verification against the raw request body and recommends deduplicating deliveries.

Never treat a public webhook URL as trusted merely because the JSON shape looks like Shopify.
