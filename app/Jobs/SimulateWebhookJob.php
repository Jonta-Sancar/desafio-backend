<?php

namespace App\Jobs;

use App\Models\PixPayment;
use App\Models\Withdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class SimulateWebhookJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public string $type;
    public int $modelId;

    public function __construct(string $type, int $modelId)
    {
        $this->type = $type;
        $this->modelId = $modelId;
    }

    public function handle(): void
    {
        if ($this->type === 'pix') {
            $pix = PixPayment::find($this->modelId);
            if (! $pix) {
                return;
            }
            $pix->update(['status' => 'CONFIRMED']);
            Log::info('Simulated webhook: pix confirmed', ['pix_id' => $pix->id]);
            return;
        }

        $wd = Withdrawal::find($this->modelId);
        if (! $wd) {
            return;
        }
        $wd->update(['status' => 'SUCCESS']);
        Log::info('Simulated webhook: withdrawal success', ['withdrawal_id' => $wd->id]);
    }
}
