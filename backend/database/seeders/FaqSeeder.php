<?php

namespace Database\Seeders;

use App\Models\FaqCategory;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'purchasing' => [
                'name' => 'Purchasing',
                'sort_order' => 1,
                'items' => [
                    ['Do I need an account to buy something?', 'No. Learn by Bluxor supports guest checkout for eligible products. Creating an account makes it easier to keep purchases together in your library.'],
                    ['Where can I see my orders?', 'Signed-in customers can view orders from their account. Guest purchases can be accessed through the available email-based purchase access or claim flow.'],
                ],
            ],
            'downloads' => [
                'name' => 'Downloads',
                'sort_order' => 2,
                'items' => [
                    ['How do I download my purchase?', 'After payment is successfully verified, open the product from your Learn by Bluxor library or use the secure access provided for the purchase.'],
                    ["My download isn't working. What should I do?", "Refresh the page, confirm you're using the correct account/email, and check your browser's download settings. If the problem continues, visit Download Help or contact support."],
                ],
            ],
            'accounts' => [
                'name' => 'Accounts',
                'sort_order' => 3,
                'items' => [
                    ['Can I sign in using Google?', 'Yes, when Google sign-in is configured and available.'],
                    ['I bought something as a guest. Can I create an account later?', 'Where supported, purchases associated with the same verified email can be claimed into your account.'],
                    ['I forgot my password. What should I do?', 'Use the Forgot Password option on the login page.'],
                ],
            ],
            'payments' => [
                'name' => 'Payments',
                'sort_order' => 4,
                'items' => [
                    ['What happens if a payment is pending?', 'Allow the payment provider some time to confirm the transaction. Avoid repeatedly paying for the same order.'],
                    ["I was charged but my order isn't active. What should I do?", 'Check the order status first. If payment was charged but access was not provided, contact support with the order reference.'],
                ],
            ],
            'products' => [
                'name' => 'Products',
                'sort_order' => 5,
                'items' => [
                    ['Are Learn by Bluxor products physical products?', 'Products are primarily digital unless the product page explicitly states otherwise.'],
                    ['Can I share a purchased ebook or file?', 'Unless the product license explicitly permits it, purchased resources are intended for the purchaser and should not be redistributed or resold.'],
                ],
            ],
            'refunds' => [
                'name' => 'Refunds',
                'sort_order' => 6,
                'items' => [
                    ['Can I request a refund?', 'Refund eligibility depends on the circumstances and our Refund Policy. See /refund-policy.'],
                    ['What if I accidentally paid twice?', 'Contact support with both transaction/order references so the duplicate payment can be reviewed.'],
                ],
            ],
        ];

        foreach ($data as $slug => $categoryData) {
            $category = FaqCategory::updateOrCreate(
                ['slug' => $slug],
                ['name' => $categoryData['name'], 'sort_order' => $categoryData['sort_order'], 'status' => 'active']
            );

            foreach ($categoryData['items'] as $index => [$question, $answer]) {
                $category->items()->updateOrCreate(
                    ['question' => $question],
                    ['answer' => $answer, 'sort_order' => $index + 1, 'status' => 'active']
                );
            }
        }
    }
}
