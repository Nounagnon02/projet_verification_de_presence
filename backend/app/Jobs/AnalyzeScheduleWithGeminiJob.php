<?php

namespace App\Jobs;

use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AnalyzeScheduleWithGeminiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 60;

    protected $filePath;
    protected $jobId;
    protected $userId;

    public function __construct(string $filePath, string $jobId, int $userId)
    {
        $this->filePath = $filePath;
        $this->jobId = $jobId;
        $this->userId = $userId;
    }

    public function handle(GeminiService $gemini): void
    {
        try {
            Cache::put("gemini_job_{$this->jobId}_status", 'processing', 600);

            $result = $gemini->analyzeSchedule(storage_path('app/' . $this->filePath));

            Cache::put("gemini_job_{$this->jobId}_status", 'completed', 600);
            Cache::put("gemini_job_{$this->jobId}_result", $result, 600);

            Log::info("Analyse Gemini terminée pour job {$this->jobId}");
        } catch (\Exception $e) {
            Cache::put("gemini_job_{$this->jobId}_status", 'failed', 600);
            Cache::put("gemini_job_{$this->jobId}_error", $e->getMessage(), 600);

            Log::error("Erreur analyse Gemini job {$this->jobId}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Cache::put("gemini_job_{$this->jobId}_status", 'failed', 600);
        Cache::put("gemini_job_{$this->jobId}_error", $exception->getMessage(), 600);
    }
}