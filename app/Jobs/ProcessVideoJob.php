<?php

namespace App\Jobs;

use App\Enums\ProtocolStageEnum;
use App\Events\VideoProcessedBroadcast;
use App\Http\Resources\ProtocolResource;
use App\Models\Protocol;
use App\Services\ProtocolService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public int $timeout = 3600;

    public function __construct(
        private readonly Protocol $protocol,
    )
    {

    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $relativePath = str_replace(config('app.url') . '/storage', '', $this->protocol->getFirstMediaUrl('video'));

        try {
            $filepath = app(ProtocolService::class)->convertToWav($relativePath);

            if (is_null($filepath)) {
                $this->protocol->update([
                    'stage' => ProtocolStageEnum::SUCCESS_VIDEO_PROCESS->value
                ]);
                broadcast(new VideoProcessedBroadcast($this->protocol));
                return;
            }
        } catch (RuntimeException $e) {
            $this->protocol->update([
                'stage' => ProtocolStageEnum::ERROR_VIDEO_PROCESS->value
            ]);

            broadcast(new VideoProcessedBroadcast($this->protocol));

            return;
        }

        $url = env('SPEECH_TRANSCRIBE_URL', 'http://127.0.0.1:8000');
        $response = Http::withOptions(['timeout' => 0])->post($url . '/transcribe/', [
            'filepath' => $filepath,
        ]);

        if($response->successful()) {
            $transcript = $response->body();
            \Log::info($transcript);
            $finalTranscript = app(ProtocolService::class)->getFinalTranscript($transcript, $this->protocol->creator_id);
            $this->protocol->update([
               'stage' => ProtocolStageEnum::SUCCESS_VIDEO_PROCESS->value,
               'final_transcript' => $finalTranscript ?? null,
               'transcript' => $transcript ?? null,
            ]);

            broadcast(new VideoProcessedBroadcast($this->protocol));
        } else {
            $this->protocol->update([
                'stage' => ProtocolStageEnum::ERROR_VIDEO_PROCESS->value,
            ]);
        }

        unlink($filepath);

    }
}
