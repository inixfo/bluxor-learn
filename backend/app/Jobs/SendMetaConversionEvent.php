<?php

namespace App\Jobs;

use App\Models\MetaConversionEvent;
use App\Services\MetaConversionsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMetaConversionEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $metaConversionEventId) {}

    public function handle(MetaConversionsService $meta): void
    {
        $event = MetaConversionEvent::find($this->metaConversionEventId);

        if ($event) {
            $meta->sendStoredEvent($event);
        }
    }
}
