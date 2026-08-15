<?php

namespace Database\Seeders;

use App\Models\HelpCategory;
use Illuminate\Database\Seeder;

class HelpCenterSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'orders-purchases' => ['Orders & Purchases', "Orders, receipts, purchase status, and accessing products you've bought.", 'shopping-cart', 1],
            'downloads-access' => ['Downloads & Access', 'Downloading files, accessing your library, and solving download problems.', 'download', 2],
            'account-login' => ['Account & Login', 'Account access, passwords, Google sign-in, and managing your profile.', 'user', 3],
            'payments' => ['Payments', 'Payment status, failed payments, pending transactions, and payment questions.', 'credit-card', 4],
            'refunds' => ['Refunds', 'Refund eligibility, requests, and what happens after a refund.', 'refresh', 5],
            'products' => ['Products', 'Product formats, requirements, updates, bundles, and other product questions.', 'package', 6],
        ];

        $models = [];
        foreach ($categories as $slug => [$name, $description, $icon, $sort]) {
            $models[$slug] = HelpCategory::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description, 'icon' => $icon, 'sort_order' => $sort, 'status' => 'active']
            );
        }

        $articles = [
            ['orders-purchases', 'how-to-access-your-purchase', 'How to access your purchase', 'Find your product after payment is verified.', 'After payment is verified, your product can appear in your account library or through a secure guest access flow. If you were signed in, open My Library. If you checked out as a guest, use the purchase email and any secure access link or claim process provided by the platform.', true, 1],
            ['downloads-access', 'how-to-download-purchased-files', 'How to download purchased files', 'Use your library or secure access link to download files.', 'Open My Library, choose the purchased product, and select the available download or access option. If a browser blocks a download, check the downloads indicator, download history, or device Downloads folder.', true, 1],
            ['orders-purchases', 'how-guest-purchases-work', 'How guest purchases work', 'Understand guest checkout and later account claiming.', 'Eligible products can be purchased without signing in. Use the same email address from checkout when accessing or claiming the purchase later. Creating an account with a verified matching email may help keep purchases together.', true, 2],
            ['payments', 'payment-pending-what-to-do', 'Payment pending - what to do', 'Avoid repeated payments while a provider is still confirming.', 'A pending payment means the provider or network has not fully confirmed the transaction yet. Wait for confirmation and check the order status before attempting another payment for the same order.', true, 1],
            ['payments', 'payment-completed-but-access-missing', 'Payment completed but access missing', 'What to check when payment succeeded but access is not visible.', 'Confirm you used the same email or account, refresh your library, and check order status. If access still does not appear, contact support with the order reference and purchase email.', false, 2],
            ['account-login', 'how-to-reset-your-password', 'How to reset your password', 'Use the forgot-password flow to regain account access.', 'Open the login page, choose Forgot password, and follow the instructions sent to your email. Never share one-time passwords, reset links, or account credentials with support.', true, 1],
            ['account-login', 'using-google-sign-in', 'Using Google sign-in', 'Sign in with a Google account when available.', 'When Google sign-in is configured, you can use a Google account to access Learn by Bluxor. If the verified Google email matches an eligible account, the platform may associate that sign-in method securely.', false, 2],
            ['refunds', 'how-refunds-work', 'How refunds work', 'Learn how refund requests are reviewed.', 'Refund eligibility depends on the Refund Policy, purchase circumstances, access state, and applicable consumer rights. Contact support with your name, purchase email, order reference, product name, and reason for the request.', true, 1],
        ];

        foreach ($articles as [$categorySlug, $slug, $title, $summary, $content, $featured, $sort]) {
            $models[$categorySlug]->articles()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'summary' => $summary,
                    'content' => $content,
                    'is_featured' => $featured,
                    'sort_order' => $sort,
                    'status' => 'published',
                ]
            );
        }
    }
}
