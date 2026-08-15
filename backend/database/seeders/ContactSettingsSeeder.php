<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContactSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'support_email' => null,
            'support_phone' => null,
            'support_whatsapp' => null,
            'business_name' => null,
            'business_address' => null,
            'support_availability_text' => 'We usually respond as soon as possible during normal support hours.',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'contact', 'key' => $key],
                ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
