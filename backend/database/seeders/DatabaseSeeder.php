<?php

namespace Database\Seeders;

use App\Models\Bundle;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\LandingPage;
use App\Models\LandingPageVersion;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ContentPageSeeder::class);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $customerRole = Role::firstOrCreate(['name' => 'customer']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@learn.bluxor.test'],
            ['name' => 'Bluxor Admin', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        );
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $customer = User::firstOrCreate(
            ['email' => 'rakib@example.com'],
            ['name' => 'Rakib Hasan', 'phone' => '01712345678', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        );
        $customer->roles()->syncWithoutDetaching([$customerRole->id]);

        $categories = collect([
            ['name' => 'AI & Automation', 'slug' => 'ai', 'sort_order' => 1],
            ['name' => 'Cybersecurity', 'slug' => 'security', 'sort_order' => 2],
            ['name' => 'Freelancing', 'slug' => 'freelance', 'sort_order' => 3],
            ['name' => 'Web Development', 'slug' => 'web', 'sort_order' => 4],
            ['name' => 'Digital Marketing', 'slug' => 'marketing', 'sort_order' => 5],
        ])->map(fn ($data) => Category::firstOrCreate(['slug' => $data['slug']], ['uuid' => (string) Str::uuid()] + $data));

        $products = collect([
            [
                'category' => 'ai',
                'name' => 'AI Automation with n8n',
                'slug' => 'ai-automation-n8n',
                'product_type' => 'ebook',
                'short_description' => 'Build real automation workflows that save hours every week.',
                'description' => 'A practical, project-based guide to building production-grade automations with n8n.',
                'regular_price_minor' => 149000,
                'sale_price_minor' => 99000,
                'cover_image_path' => 'https://images.pexels.com/photos/8879249/pexels-photo-8879249.jpeg?auto=compress&cs=tinysrgb&w=800',
                'featured' => true,
                'tags' => ['n8n', 'automation', 'ai', 'workflow'],
            ],
            [
                'category' => 'security',
                'name' => 'Practical Bug Bounty Hunting',
                'slug' => 'practical-bug-bounty',
                'product_type' => 'guide',
                'short_description' => 'Find and report real vulnerabilities from basics to payouts.',
                'description' => 'Learn how professional bug bounty hunters think, recon, and report.',
                'regular_price_minor' => 199000,
                'sale_price_minor' => 129000,
                'cover_image_path' => 'https://images.pexels.com/photos/5380642/pexels-photo-5380642.jpeg?auto=compress&cs=tinysrgb&w=800',
                'featured' => true,
                'tags' => ['security', 'bugbounty', 'hacking', 'web'],
            ],
            [
                'category' => 'security',
                'name' => 'Cybersecurity Essentials',
                'slug' => 'cybersecurity-essentials',
                'product_type' => 'ebook',
                'short_description' => 'Defend systems with practical, hands-on security fundamentals.',
                'description' => 'A hands-on introduction to modern cybersecurity.',
                'regular_price_minor' => 129000,
                'sale_price_minor' => 89000,
                'cover_image_path' => 'https://images.pexels.com/photos/60504/security-protection-anti-virus-software-60504.jpeg?auto=compress&cs=tinysrgb&w=800',
                'featured' => false,
                'tags' => ['security', 'defense', 'network'],
            ],
            [
                'category' => 'freelance',
                'name' => 'Freelance Mastery (Bangladesh)',
                'slug' => 'freelance-mastery-bd',
                'product_type' => 'guide',
                'short_description' => 'Land international clients and earn in USD from Bangladesh.',
                'description' => 'A practical playbook for Bangladeshi freelancers.',
                'regular_price_minor' => 99000,
                'sale_price_minor' => 69000,
                'cover_image_path' => 'https://images.pexels.com/photos/4348404/pexels-photo-4348404.jpeg?auto=compress&cs=tinysrgb&w=800',
                'featured' => true,
                'tags' => ['freelance', 'upwork', 'income', 'bd'],
            ],
            [
                'category' => 'web',
                'name' => 'React Templates Pack',
                'slug' => 'react-templates-pack',
                'product_type' => 'template',
                'short_description' => 'Production-ready React components and layouts for SaaS.',
                'description' => 'A curated pack of responsive React and Tailwind templates.',
                'regular_price_minor' => 179000,
                'sale_price_minor' => 119000,
                'cover_image_path' => 'https://images.pexels.com/photos/1181271/pexels-photo-1181271.jpeg?auto=compress&cs=tinysrgb&w=800',
                'featured' => false,
                'tags' => ['react', 'tailwind', 'templates', 'saas'],
            ],
        ])->map(function ($data) use ($categories) {
            $tags = $data['tags'];
            unset($data['tags']);
            $category = $categories->firstWhere('slug', $data['category']);
            unset($data['category']);

            $product = Product::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'category_id' => $category->id,
                    'status' => 'published',
                    'currency' => 'BDT',
                    'published_at' => now()->subDays(random_int(1, 30)),
                ] + $data
            );

            $tagIds = collect($tags)->map(fn ($tag) => Tag::firstOrCreate(['slug' => Str::slug($tag)], ['name' => $tag])->id);
            $product->tags()->sync($tagIds);
            $product->files()->firstOrCreate(
                ['name' => Str::slug($product->name).'.pdf'],
                [
                    'uuid' => (string) Str::uuid(),
                    'file_type' => 'PDF',
                    'file_size_bytes' => 29360128,
                    'storage_disk' => 'private',
                    'storage_path' => 'products/'.$product->slug.'/main.pdf',
                    'version' => '1.0.0',
                    'status' => 'active',
                ]
            );

            return $product;
        });

        $completeBundle = Bundle::updateOrCreate(
            ['slug' => 'complete-learning-bundle'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Complete Learning Bundle',
                'description' => 'Automation, security, and freelancing in one discounted bundle.',
                'status' => 'published',
                'cover_image_path' => 'https://images.pexels.com/photos/7681091/pexels-photo-7681091.jpeg?auto=compress&cs=tinysrgb&w=800',
                'regular_value_minor' => 447000,
                'bundle_price_minor' => 249000,
                'currency' => 'BDT',
                'published_at' => now(),
            ]
        );
        $completeBundle->products()->sync($products->whereIn('slug', ['ai-automation-n8n', 'practical-bug-bounty', 'freelance-mastery-bd'])->pluck('id'));

        Coupon::updateOrCreate(
            ['code' => 'LAUNCH20'],
            [
                'type' => 'percent',
                'percentage_bps' => 2000,
                'status' => 'active',
                'usage_limit' => 500,
                'minimum_order_minor' => 50000,
                'currency' => 'BDT',
                'starts_at' => now()->subMonth(),
                'expires_at' => now()->addMonth(),
            ]
        );

        $product = $products->firstWhere('slug', 'ai-automation-n8n');
        $order = Order::updateOrCreate(
            ['order_number' => 'LBLX-2026-000001'],
            [
                'uuid' => (string) Str::uuid(),
                'user_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'order_status' => 'completed',
                'payment_status' => 'paid',
                'subtotal_minor' => 99000,
                'discount_minor' => 0,
                'total_minor' => 99000,
                'currency' => 'BDT',
            ]
        );

        $item = $order->items()->firstOrCreate(
            ['product_slug' => $product->slug],
            [
                'purchasable_type' => 'product',
                'purchasable_id' => $product->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'unit_price_minor' => 99000,
                'total_minor' => 99000,
                'currency' => 'BDT',
            ]
        );

        DB::table('entitlements')->updateOrInsert(
            ['order_item_id' => $item->id, 'product_id' => $product->id],
            [
                'uuid' => (string) Str::uuid(),
                'user_id' => $customer->id,
                'order_id' => $order->id,
                'customer_email' => $customer->email,
                'status' => 'active',
                'granted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $landingPage = LandingPage::updateOrCreate(
            ['slug' => 'n8n-automation'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'n8n Automation Sales Page',
                'status' => 'published',
                'primary_product_id' => $product->id,
            ]
        );

        $version = LandingPageVersion::updateOrCreate(
            ['landing_page_id' => $landingPage->id, 'version_number' => 1],
            [
                'uuid' => (string) Str::uuid(),
                'package_path' => 'landing-pages/n8n-automation/v1.zip',
                'manifest' => ['schemaVersion' => 2, 'name' => 'n8n Automation Sales Page', 'sdkVersion' => '2', 'entry' => 'dist/index.html'],
                'entry_path' => 'dist/index.html',
                'checksum' => hash('sha256', 'seed-package'),
                'sdk_version' => '2',
                'status' => 'published',
                'created_by' => $admin->id,
                'published_at' => now(),
            ]
        );

        $landingPage->forceFill(['published_version_id' => $version->id])->save();

        DB::table('settings')->updateOrInsert(['group' => 'general', 'key' => 'site_name'], ['value' => json_encode('Learn by Bluxor'), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('settings')->updateOrInsert(['group' => 'general', 'key' => 'timezone'], ['value' => json_encode('Asia/Dhaka'), 'created_at' => now(), 'updated_at' => now()]);

        $this->call([
            ContactSettingsSeeder::class,
            FaqSeeder::class,
            HelpCenterSeeder::class,
        ]);
    }
}
