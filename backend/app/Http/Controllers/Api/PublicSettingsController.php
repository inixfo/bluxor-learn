<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class PublicSettingsController extends Controller
{
    public function contact()
    {
        $allowed = [
            'support_email',
            'support_phone',
            'support_whatsapp',
            'business_name',
            'business_address',
            'support_availability_text',
        ];

        $rows = DB::table('settings')
            ->where('group', 'contact')
            ->whereIn('key', $allowed)
            ->get()
            ->mapWithKeys(fn ($row) => [$row->key => json_decode($row->value, true)])
            ->all();

        return response()->json(['data' => array_merge(array_fill_keys($allowed, null), $rows)]);
    }
}
