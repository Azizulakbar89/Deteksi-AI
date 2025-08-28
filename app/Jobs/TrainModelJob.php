<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\TrainingResult;
use App\Models\Image;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Exception\ProcessFailedException;

class TrainModelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $split;

    public function __construct($split)
    {
        $this->split = $split;
        $this->onQueue('training');
    }

    public function handle()
    {
        Log::info("Starting training job with split: " . $this->split);

        try {
            $pythonDir = base_path('python');
            $trainScript = $pythonDir . '/train.py';

            if (!file_exists($trainScript)) {
                throw new \Exception("Train script not found at: " . $trainScript);
            }

            $pythonPath = env('PYTHON_VENV_PATH', env('PYTHON_PATH', '/usr/bin/python3'));

            if (!file_exists($pythonPath)) {
                throw new \Exception("Python executable not found at: " . $pythonPath);
            }

            $env = [
                'PYTHONPATH' => dirname($pythonPath) . '/../lib/python3.11/site-packages',
                'LD_LIBRARY_PATH' => '/usr/local/cuda/lib64:/usr/local/cuda/lib',
                'TF_FORCE_GPU_ALLOW_GROWTH' => 'true',
            ];

            $process = Process::timeout(10800)
                ->env($env)
                ->command([
                    $pythonPath,
                    $trainScript,
                    (string) $this->split
                ]);

            $result = $process->run();

            $output = trim($result->output());
            $errorOutput = trim($result->errorOutput());

            Log::info("Python output: " . $output);
            if (!empty($errorOutput)) {
                Log::warning("Python error output: " . $errorOutput);
            }

            if (!$result->successful()) {
                throw new ProcessFailedException($process);
            }

            $cleanedOutput = $this->removeAnsiEscapeCodes($output);

            $jsonStart = strpos($cleanedOutput, '{');
            $jsonEnd = strrpos($cleanedOutput, '}');

            if ($jsonStart === false || $jsonEnd === false) {
                Log::error("Raw output for debugging: " . $cleanedOutput);
                throw new \Exception('No JSON found in output');
            }

            $jsonString = substr($cleanedOutput, $jsonStart, $jsonEnd - $jsonStart + 1);

            $resultData = json_decode($jsonString, true);

            if (!$resultData || json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Raw JSON string for debugging: " . $jsonString);
                Log::error("JSON error: " . json_last_error_msg());
                throw new \Exception('Invalid JSON output: ' . json_last_error_msg());
            }

            if (isset($resultData['error'])) {
                throw new \Exception($resultData['error']);
            }

            DB::transaction(function () use ($resultData) {
                $trainingResult = TrainingResult::create([
                    'accuracy'       => $resultData['accuracy'] ?? 0,
                    'precision'      => $resultData['precision'] ?? 0,
                    'recall'         => $resultData['recall'] ?? 0,
                    'f1_score'       => $resultData['f1_score'] ?? 0,
                    'confusion_matrix' => json_encode($resultData['confusion_matrix'] ?? []),
                    'split_ratio'    => $this->split
                ]);

                Log::info("Training result saved with ID: " . $trainingResult->id);

                if (!empty($resultData['predictions'])) {
                    $this->updatePredictions($resultData['predictions']);
                    Log::info("Updated " . count($resultData['predictions']) . " predictions");
                }
            });

            Log::info("Training completed successfully");
        } catch (\Exception $e) {
            Log::error("Training job failed: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            throw $e;
        }
    }

    private function removeAnsiEscapeCodes($string)
    {
        return preg_replace('/\x1b\[[0-9;]*m/', '', $string);
    }

    private function updatePredictions($predictions)
    {
        $batchSize = 50;
        foreach (array_chunk($predictions, $batchSize) as $batch) {
            foreach ($batch as $p) {
                if (isset($p['filename']) && isset($p['prediction']) && isset($p['confidence'])) {
                    Image::where('filename', $p['filename'])
                        ->where('split_ratio', $this->split)
                        ->update([
                            'prediction' => $p['prediction'],
                            'confidence' => $p['confidence']
                        ]);
                }
            }
            unset($batch);
            gc_collect_cycles();
        }
    }
}
