<?php

namespace Database\Seeders;

use App\Models\ContentPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContentPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => $data) {
            $page = ContentPage::firstOrNew(['slug' => $slug]);

            if (! $page->exists) {
                $page->uuid = (string) Str::uuid();
            }

            $page->fill($data + ['slug' => $slug, 'status' => 'published']);
            $page->save();
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function pages(): array
    {
        return [
            'about' => [
                'title' => 'About Learn by Bluxor',
                'meta_title' => 'About Learn by Bluxor | Practical Digital Learning',
                'meta_description' => 'Learn about Learn by Bluxor, a practical digital learning platform for guides, templates, resources, automation, technology, and digital skills.',
                'content' => <<<'MD'
Eyebrow: About Learn by Bluxor

# Practical knowledge you can actually use.

Learn by Bluxor is a digital learning platform by Bluxor, built for people who want practical knowledge without unnecessary complexity.

We create ebooks, guides, templates, resources, and other digital products around technology, automation, online business, cybersecurity, productivity, and related skills.

Our goal is simple: turn complicated subjects into useful, understandable resources that help you learn faster and put what you learn into practice.

## Learning should lead to action.

There is more information online than ever before. The difficult part is not finding information - it is finding the right information, understanding it, and knowing what to do next.

That is why Learn by Bluxor focuses on practical learning.

Instead of filling products with unnecessary theory, we aim to explain concepts clearly, show how they work, and provide examples that can be applied in real situations.

## What you'll find here

### Practical Guides

Step-by-step ebooks and guides designed to make complex subjects easier to understand.

### Automation & Technology

Resources covering AI, automation tools, workflows, software, and modern technology.

### Digital Skills

Practical material for improving skills related to online work, business, development, marketing, and productivity.

### Ready-to-use Resources

Templates, files, examples, and supporting materials that help you move from learning to implementation.

## Made to be useful, not just readable.

Every Learn by Bluxor product is designed around a practical outcome.

Depending on the subject, a resource may include:

* step-by-step instructions
* screenshots and visual explanations
* examples
* templates
* downloadable files
* workflows
* checklists
* practical projects

Our products may evolve over time as tools and technologies change.

When an updated version is made available to an eligible purchase, it can be delivered through the customer's Learn by Bluxor library.

Do not promise lifetime updates unless the product explicitly includes them.

## Our principles

### Clear

Explain things in language people can understand.

### Practical

Focus on knowledge that can be applied.

### Useful

Avoid unnecessary filler.

### Responsible

Technology should be taught and used responsibly.

## Ready to learn something useful?

Browse our growing collection of practical digital resources.
MD,
            ],
            'contact' => [
                'title' => 'Contact Learn by Bluxor',
                'meta_title' => 'Contact Learn by Bluxor',
                'meta_description' => 'Contact Learn by Bluxor for help with products, purchases, downloads, account access, and general support questions.',
                'content' => <<<'MD'
Eyebrow: Contact

# How can we help?

Have a question about a product, purchase, download, or your account? Send us a message and we'll help you find the right solution.

## Support topics

### Product & Purchase Support

Questions about a product before or after purchasing.

### Download Support

Problems accessing or downloading something you've purchased.

### Account Support

Questions about your Learn by Bluxor account, library, or orders.

### General Questions

Anything else related to Learn by Bluxor.

## Before contacting us

You may find an immediate answer in our Help Center or FAQ.
MD,
            ],
            'help' => [
                'title' => 'Help Center',
                'meta_title' => 'Help Center | Learn by Bluxor',
                'meta_description' => 'Find quick answers about Learn by Bluxor purchases, downloads, accounts, payments, refunds, and product access.',
                'content' => <<<'MD'
Eyebrow: Help Center

# What do you need help with?

Find quick answers about purchases, downloads, accounts, payments, and using Learn by Bluxor.

## Purchases & Orders

Learn where to find your orders, receipts, and purchased products.

After a successful purchase, your order can be viewed from your Learn by Bluxor account when the purchase is associated with that account.

Guest purchases may also be claimable using the email associated with the purchase where supported by the platform.

CTA: View My Orders -> /account/orders

## Downloads

Having trouble accessing a digital product?

CTA: Download Help -> /download-help

## Account

Manage your account information, password, library, and sign-in methods.

CTA: Open My Account -> /account

## Payments

If a payment does not complete successfully, do not repeatedly pay for the same order.

Check your order status first. If the payment was charged but your order was not activated, contact support with your order information.

## Refunds

Refund eligibility depends on the circumstances of the purchase and the Refund Policy.

CTA: Read Refund Policy -> /refund-policy

## Product Questions

Questions about what a product contains or whether it is suitable for you?

Check the product page first. If you still need help, contact us before purchasing.

## Still need help?

We're here to help with account, purchase, and download issues.
MD,
            ],
            'faq' => [
                'title' => 'Frequently Asked Questions',
                'meta_title' => 'Frequently Asked Questions | Learn by Bluxor',
                'meta_description' => 'Answers to common questions about Learn by Bluxor purchasing, downloads, accounts, payments, products, and refunds.',
                'content' => <<<'MD'
Eyebrow: FAQ

# Frequently asked questions.

Answers to common questions about purchases, downloads, accounts, payments, products, and refunds.

## Purchasing

### How do I purchase a product?

Choose the product you want, continue to checkout, enter the required information, and complete payment using one of the available payment methods.

After a successful payment and verification, access will be provided according to the product's delivery method.

### Do I need an account to purchase?

Not necessarily.

Learn by Bluxor supports guest checkout for eligible purchases. Creating or signing into an account makes it easier to keep your purchases together in your library.

### Where can I see my purchases?

Signed-in customers can view their purchases from their account, library, or orders section.

If you purchased as a guest, use the email associated with the order and the available purchase-claim/access process.

## Downloads

### How do I download something I purchased?

After your payment is successfully verified, open the purchased product from your Learn by Bluxor library or the access link provided after purchase.

### My download isn't working. What should I do?

First try refreshing the page, signing back into the correct account, and using an updated browser.

If the problem continues, visit Download Help or contact support with your order information.

### Can I share my downloaded product with someone else?

Unless a product specifically says otherwise, purchases are licensed for the purchaser's own use.

Do not redistribute, resell, publicly upload, or share paid Learn by Bluxor files without permission.

## Accounts

### Can I use Google to sign in?

Yes, when Google sign-in is available you can use it to access Learn by Bluxor.

If the verified Google email matches an eligible existing account, the platform may securely associate the sign-in method with that account.

### I purchased as a guest. Can I create an account later?

Where supported, purchases made using the same verified email may be claimable into your Learn by Bluxor account.

### What if I forget my password?

Use the Forgot password option on the login page and follow the instructions sent to your email.

## Payments

### What happens if my payment fails?

A failed or incomplete payment does not normally activate the purchase.

You can check the order status and try again if appropriate.

If you were charged but access was not provided, contact support before attempting multiple additional payments.

### Is access provided immediately?

Most digital products are made available after the payment provider confirms the transaction and Learn by Bluxor successfully verifies it.

Occasional payment-provider or network delays may delay confirmation.

## Products

### Are the products physical products?

Products sold through Learn by Bluxor are primarily digital unless a product page explicitly states otherwise.

No physical shipment should be expected for a digital-only product.

### Will a product always stay exactly the same?

Digital products may occasionally be corrected, improved, or updated.

The availability of future updates depends on the specific product and does not automatically mean every purchase includes permanent or lifetime updates.

## Refunds

### Can I get a refund?

Because many Learn by Bluxor products are digital and can be accessed immediately, refunds are handled according to the Refund Policy and the circumstances of the purchase.

See: /refund-policy

### What if I purchased the same product twice?

Contact support with the relevant order details. We can review duplicate-payment situations.
MD,
            ],
            'download-help' => [
                'title' => 'Download Help',
                'meta_title' => 'Download Help | Learn by Bluxor',
                'meta_description' => 'Troubleshoot Learn by Bluxor download and access issues with purchases, accounts, libraries, browsers, and support requests.',
                'content' => <<<'MD'
Eyebrow: Download Help

# Having trouble accessing your purchase?

Most download and access problems can be solved in a few minutes. Follow these steps before contacting support.

## Step 1

### Confirm the payment completed

Your digital product is normally made available only after the payment has been successfully verified.

Check your order status.

If it is still pending, wait for the payment provider to finish processing before trying to purchase again.

## Step 2

### Make sure you're using the correct account

If you purchased while signed in, log in using the same Learn by Bluxor account.

If you purchased as a guest, use the same email address that was used during checkout when using the available purchase-access or claim process.

## Step 3

### Open your library

Go to:

My Library

Find the purchased product and select its available download/access option.

## Step 4

### Try your browser again

If clicking Download does nothing:

* refresh the page
* disable aggressive popup/download blocking temporarily
* try a current version of Chrome, Edge, Firefox, or Safari
* try another device or network if necessary

Do not instruct users to disable security software entirely.

## Step 5

### Check your downloads folder

Your browser may download the file without opening it automatically.

Check your browser's download history and device Downloads folder.

## Step 6

### Still not working?

Contact support.

Provide:

* email used for purchase
* order reference if available
* product name
* description of the issue

Do NOT ask customers to send:

* passwords
* full payment credentials
* OTP codes
* authentication codes

## Keep your account secure

Learn by Bluxor support will never need your password, one-time password, or complete payment credentials to help with a download issue.
MD,
            ],
            'terms' => [
                'title' => 'Terms of Use',
                'meta_title' => 'Terms of Use | Learn by Bluxor',
                'meta_description' => 'Read the Learn by Bluxor Terms of Use for accounts, purchases, digital products, permitted use, refunds, and platform access.',
                'content' => <<<'MD'
# Terms of Use

These Terms of Use govern your access to and use of Learn by Bluxor, including our website, accounts, digital products, downloads, and related services.

By using Learn by Bluxor or purchasing a product, you agree to these Terms.

If you do not agree with them, please do not use the service.

## 1. About Learn by Bluxor

Learn by Bluxor is a digital learning platform operated by Bluxor.

We provide digital products such as ebooks, guides, templates, educational resources, downloadable files, and related content.

## 2. Accounts

Some features require an account, while eligible purchases may support guest checkout.

You are responsible for:

* providing accurate information
* maintaining the security of your account
* keeping your login credentials confidential
* activities performed through your account

You must not use another person's account without authorization.

## 3. Purchases

Product descriptions, contents, pricing, and availability are shown on the relevant product or checkout page.

Before completing a purchase, you are responsible for reviewing the product information and confirming that the product is suitable for your needs.

A purchase is considered successfully completed only after payment has been confirmed and accepted by our systems.

## 4. Digital Products

Unless explicitly stated otherwise, Learn by Bluxor products are digital.

Digital products may be delivered through:

* an account library
* a secure download
* another digital access method described on the product page

No physical shipment is provided for products described as digital.

## 5. License and permitted use

Unless a product states otherwise, purchasing a digital product gives you a limited, personal, non-transferable right to use that product.

The purchase does not transfer ownership of the underlying intellectual property.

You may not, without permission:

* resell the product
* redistribute paid files
* upload them publicly
* share purchased access with others
* copy substantial portions for commercial distribution
* claim the material as your own

Different licensing terms may apply where explicitly stated on a product page or accompanying license.

## 6. Educational information

Our products are created for educational and informational purposes.

Results depend on many factors including how information is understood, applied, the learner's circumstances, and changes in third-party tools or platforms.

Purchasing a product does not guarantee a particular:

* income
* business result
* employment result
* technical outcome
* ranking
* financial result

## 7. Technology and third-party services

Some products may discuss or rely on third-party software, websites, platforms, APIs, services, or tools.

Those third parties can change their:

* pricing
* functionality
* policies
* availability
* interfaces

Learn by Bluxor does not control those third-party services.

Where possible, products may be updated when significant changes occur, but updates are not guaranteed unless explicitly included with the product.

## 8. Responsible use

You agree not to use Learn by Bluxor or our materials to:

* violate applicable law
* infringe another person's rights
* gain unauthorized access to systems or accounts
* distribute malware
* interfere with the platform
* abuse payment, account, or download systems

Educational cybersecurity material must be used only in environments and systems where the user has appropriate authorization.

## 9. Refunds

Refund requests are handled according to our Refund Policy.

See: /refund-policy

Nothing in these Terms is intended to remove consumer rights that cannot lawfully be excluded under applicable law.

## 10. Intellectual property

The Learn by Bluxor website, branding, original written content, graphics, product materials, and other original resources are protected by applicable intellectual-property rights.

Third-party names, trademarks, and content remain the property of their respective owners.

## 11. Service availability

We work to keep Learn by Bluxor available, but uninterrupted access cannot be guaranteed.

Temporary interruptions may occur because of:

* maintenance
* hosting problems
* internet failures
* payment-provider outages
* software problems
* security incidents
* circumstances outside our reasonable control

## 12. Changes to products and service

We may improve, modify, replace, or discontinue parts of the platform.

Where a change materially affects an existing paid entitlement, we will aim to handle it reasonably.

## 13. Changes to these Terms

These Terms may be updated when our platform, products, or legal requirements change.

The latest version will be published on this page with the appropriate updated date.

## 14. Contact

Questions about these Terms can be submitted through:

/contact
MD,
            ],
            'privacy' => [
                'title' => 'Privacy Policy',
                'meta_title' => 'Privacy Policy | Learn by Bluxor',
                'meta_description' => 'Learn how Learn by Bluxor may collect, use, retain, and protect account, purchase, contact, payment, and technical information.',
                'content' => <<<'MD'
# Privacy Policy

This Privacy Policy explains the types of information Learn by Bluxor may collect, why we use it, and the choices available to you when you use our website, create an account, purchase a product, or contact us.

## 1. Information you provide

We may receive information you provide directly, including:

* name
* email address
* mobile number
* account information
* contact-form messages
* order and purchase information
* information needed to provide customer support

## 2. Account information

If you create an account, we store information necessary to operate that account and provide access to purchased products.

Passwords should be stored using secure one-way hashing through the application's authentication system and should never be stored as readable plain-text passwords.

Do not claim a specific hashing algorithm in public content unless verified from implementation.

## 3. Google sign-in

If you choose to sign in using Google, we may receive basic identity information authorized by you and provided by Google, such as:

* name
* email address
* Google account identifier
* profile image, when available

We use this information to authenticate you and connect the Google identity with your Learn by Bluxor account.

We do not request access to Gmail, Google Drive, Google Contacts, or other unrelated Google services simply for Google sign-in.

## 4. Purchases and payment information

We collect information necessary to create and manage your order.

Payment transactions may be processed through third-party payment providers.

Learn by Bluxor should not claim to store full card or banking credentials when those credentials are handled directly by the payment provider.

We may retain:

* transaction references
* payment status
* amount
* currency
* order details

where necessary for purchase verification, accounting, support, fraud prevention, and refund handling.

## 5. Automatically collected information

When you use the website, servers and supporting services may automatically process technical information such as:

* IP address
* browser/device information
* timestamps
* requested pages
* application logs
* security-related information

This information may be used to operate, secure, troubleshoot, and improve the service.

## 6. Cookies and sessions

Learn by Bluxor may use cookies or similar browser storage necessary for:

* authentication
* sessions
* security
* checkout state
* preferences
* basic site functionality

Additional analytics technologies should be described here if they are enabled in production.

Do not claim advertising/tracking cookies are used unless they actually are.

## 7. How we use information

Information may be used to:

* provide accounts
* process orders
* provide purchased content
* verify payments
* respond to support requests
* send transactional emails
* prevent fraud and abuse
* maintain platform security
* improve platform reliability and usability
* meet applicable legal obligations

## 8. When information may be shared

Information may be processed by service providers necessary to operate Learn by Bluxor, such as:

* hosting providers
* email providers
* authentication providers
* payment providers
* infrastructure or security providers

We do not sell personal information to advertisers.

Only include this statement if the business intends to follow this practice.

## 9. Data retention

We keep information for as long as reasonably necessary for the purpose it was collected, including providing purchases, maintaining account records, resolving disputes, preventing abuse, and meeting applicable legal or accounting obligations.

Retention periods may vary depending on the type of information.

## 10. Security

We use reasonable technical and organizational safeguards designed to protect account, purchase, and platform information.

However, no internet-connected service can guarantee absolute security.

Users should protect their passwords and avoid sharing authentication codes.

## 11. Your choices

Depending on the feature and applicable law, you may be able to:

* update account details
* change your password
* contact us regarding your information
* request correction of inaccurate information
* request account assistance

Requests may require identity verification before action is taken.

Do not promise deletion where information must lawfully or operationally be retained.

## 12. Children's privacy

Learn by Bluxor is not intentionally designed to collect personal information from young children.

If a parent or guardian believes a child has provided personal information inappropriately, they may contact us.

Do not specify a fixed age threshold unless the business has selected one based on its legal requirements.

## 13. Changes to this policy

We may update this Privacy Policy as our services, technology, and requirements change.

The latest version will be published on this page.

## 14. Contact

Questions about privacy can be submitted through:

/contact
MD,
            ],
            'refund-policy' => [
                'title' => 'Refund Policy',
                'meta_title' => 'Refund Policy | Learn by Bluxor',
                'meta_description' => 'Review the Learn by Bluxor Refund Policy for digital purchases, duplicate payments, delivery issues, unusable files, and access after refunds.',
                'content' => <<<'MD'
# Refund Policy

We want customers to understand what they are purchasing and to receive working access to the digital product they paid for.

Because Learn by Bluxor primarily sells digital products that may be delivered or made accessible immediately, refunds are handled based on the circumstances described below.

Nothing in this policy is intended to limit rights that cannot lawfully be excluded under applicable consumer law.

## Digital purchases

Because digital products can become accessible immediately after payment, refund eligibility is more limited than it may be for physical goods.

Each request is reviewed according to this policy and applicable consumer rights.

## When a refund may be considered

### Duplicate payment

You were charged more than once for the same intended purchase.

### Product not delivered

Payment was successfully completed but the purchased digital product was not made available, and we are unable to resolve the access problem.

### File is unusable

A purchased file is materially corrupted or technically unusable and we cannot provide a working replacement.

### Incorrect product delivered

The product delivered is materially different from the product associated with the completed order because of an error on our side.

### Other exceptional circumstances

We may review other reasonable requests individually.

## Situations that normally do not qualify

A refund will not normally be provided simply because:

* you changed your mind after receiving/accessing the digital product
* you did not read the product description before purchasing
* you expected information or features that were not advertised
* you no longer need the product
* you lack the software, device, skill, or third-party service needed to use the product where those requirements were reasonably disclosed
* a third-party platform later changes its interface, pricing, policies, or features
* you purchased the product and then found similar information elsewhere

This does not override rights available under applicable law.

## Before requesting a refund

If your problem involves:

* downloading
* accessing your library
* receiving a file
* payment confirmation

please contact support first.

Many access problems can be resolved without a refund.

## How to request a refund

Go to:

/contact

Provide:

* your name
* purchase email
* order/reference number where available
* product name
* reason for the request

Do not send passwords, OTP codes, or full payment credentials.

## Review process

We may verify:

* the order
* payment status
* delivery/access status
* previous refund activity
* relevant technical records

before making a decision.

If a refund is approved, it will be processed through the available payment/refund process.

Processing time after approval may depend on the payment provider or financial institution.

## Access after refund

When a digital purchase is refunded, access to the corresponding paid product may be removed from the customer's Learn by Bluxor account or library.

## Abuse

We may refuse requests involving apparent:

* refund abuse
* fraudulent transactions
* repeated misuse
* unauthorized redistribution of purchased content

subject to applicable consumer rights and law.

## Questions

If you're unsure whether your situation qualifies, contact us and explain the issue.
MD,
            ],
        ];
    }
}
