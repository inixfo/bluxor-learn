<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MetaConversionEvent;
use App\Services\MetaConversionsService;
use App\Services\MetaTrackingSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackingController extends Controller
{
    public function __construct(
        private readonly MetaTrackingSettings $settings,
        private readonly MetaConversionsService $metaConversions
    ) {}

    public function config(Request $request)
    {
        return response()->json(['data' => $this->settings->publicPayload($request)])
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function adminStatus()
    {
        return response()->json(['data' => [
            'meta' => $this->settings->adminPayload(),
            'recent_events' => MetaConversionEvent::latest()
                ->limit(10)
                ->get(['event_name', 'event_id', 'order_id', 'status', 'attempts', 'last_error_code', 'last_error_message', 'sent_at', 'created_at']),
        ]]);
    }

    public function updateAdminSettings(Request $request)
    {
        $data = $request->validate([
            'pixel_enabled' => ['required', 'boolean'],
            'pixel_id' => ['nullable', 'string', 'max:80'],
            'capi_enabled' => ['required', 'boolean'],
            'graph_api_version' => ['nullable', 'regex:/^v\d+\.\d+$/'],
        ]);

        foreach ($data as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'tracking', 'key' => $key],
                ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]
            );
        }

        return $this->adminStatus();
    }

    public function sendTestEvent()
    {
        $result = $this->metaConversions->sendTestEvent();

        return response()->json(['data' => $result]);
    }
}
