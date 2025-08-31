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
use Exception;

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
                throw new Exception("Train script not found at: " . $trainScript);
            }


            $venvPath = env('PYTHON_VENV_PATH', 'C:\\Deteksi-AI\\python\\venv-311');
            $pythonPath = $venvPath . '\\Scripts\\python.exe';

            if (!file_exists($pythonPath)) {
                $pythonPath = env('PYTHON_PATH', 'C:\\Users\\Jijul\\AppData\\Local\\Programs\\Python\\Python311\\python.exe');
                
                if (!file_exists($pythonPath)) {
                    throw new Exception("Python executable not found at: " . $pythonPath);
                }
            }

            Log::info("Using Python executable: " . $pythonPath);

            $command = [
                '"' . $pythonPath . '"',
                '"' . $trainScript . '"',
                (string) $this->split
            ];

            Log::info("Executing command: " . implode(' ', $command));

            $result = Process::timeout(10800)
                ->env([
                    'PATH' => $venvPath . '\\Scripts;' . getenv('PATH')
                ])
                ->command($command)
                ->run();

        } catch (Exception $e) {
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