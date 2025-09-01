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
use Illuminate\Support\Facades\File;

class TrainModelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $split;

    /**
     * Constructor untuk job training model
     * 
     * @param int $split Split ratio untuk training (90, 80, atau 70)
     */
    public function __construct($split)
    {
        $this->split = $split;
        $this->onQueue('training'); // Menetapkan job ke queue 'training'
    }

    /**
     * Method utama yang dieksekusi ketika job dijalankan
     * 
     * Menangani seluruh proses training model machine learning
     */
    public function handle()
    {
        Log::info("Starting training job with split: " . $this->split);

        try {
            // Mendefinisikan path direktori Python dan script training
            $pythonDir = base_path('python');
            $trainScript = $pythonDir . '/train.py';
            $modelDir = $pythonDir . '/model';

            // Menghapus model lama dengan split ratio yang sama
            $this->deleteOldModel($modelDir, $this->split);

            // Memvalidasi keberadaan script training
            if (!file_exists($trainScript)) {
                throw new \Exception("Train script not found at: " . $trainScript);
            }

            // Mendapatkan path Python executable dari environment variable
            $pythonPath = env('PYTHON_VENV_PATH', 'C:\\Users\\Jijul\\AppData\\Local\\Programs\\Python\\Python311\\python.exe');

            // Memvalidasi keberadaan Python executable
            if (!file_exists($pythonPath)) {
                throw new \Exception("Python executable not found at: " . $pythonPath);
            }

            // Menyiapkan environment variables untuk proses Python
            $env = [
                'PYTHONPATH' => 'C:\\Users\\Jijul\\AppData\\Local\\Programs\\Python\\Python311\\Lib\\site-packages',
                'TF_FORCE_GPU_ALLOW_GROWTH' => 'true', // Mengizinkan TensorFlow menggunakan GPU memory secara dinamis
            ];

            // Menjalankan proses training Python dengan timeout 3 jam (10800 detik)
            $process = Process::timeout(10800)
                ->env($env)
                ->command([
                    $pythonPath,
                    $trainScript,
                    (string) $this->split // Mengirim split ratio sebagai parameter
                ]);

            // Menjalankan proses dan mendapatkan hasil
            $result = $process->run();

            // Mengambil output standar dan error
            $output = trim($result->output());
            $errorOutput = trim($result->errorOutput());

            // Log output dari proses Python
            Log::info("Python output: " . $output);
            if (!empty($errorOutput)) {
                Log::warning("Python error output: " . $errorOutput);
            }

            // Memeriksa apakah proses berhasil
            if (!$result->successful()) {
                throw new ProcessFailedException($process);
            }

            // Membersihkan output dari kode escape ANSI (warna terminal)
            $cleanedOutput = $this->removeAnsiEscapeCodes($output);

            // Mencari dan mengekstrak JSON dari output
            $jsonStart = strpos($cleanedOutput, '{');
            $jsonEnd = strrpos($cleanedOutput, '}');

            if ($jsonStart === false || $jsonEnd === false) {
                Log::error("Raw output for debugging: " . $cleanedOutput);
                throw new \Exception('No JSON found in output');
            }

            $jsonString = substr($cleanedOutput, $jsonStart, $jsonEnd - $jsonStart + 1);

            // Mendecode JSON string ke array PHP
            $resultData = json_decode($jsonString, true);

            // Validasi JSON
            if (!$resultData || json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Raw JSON string for debugging: " . $jsonString);
                Log::error("JSON error: " . json_last_error_msg());
                throw new \Exception('Invalid JSON output: ' . json_last_error_msg());
            }

            // Memeriksa jika ada error dalam hasil training
            if (isset($resultData['error'])) {
                throw new \Exception($resultData['error']);
            }

            // Menyimpan hasil training ke database menggunakan transaction
            DB::transaction(function () use ($resultData) {
                $trainingResult = TrainingResult::create([
                    'accuracy'       => $resultData['accuracy'] ?? 0, // Akurasi model
                    'precision'      => $resultData['precision'] ?? 0, // Presisi model
                    'recall'         => $resultData['recall'] ?? 0, // Recall model
                    'f1_score'       => $resultData['f1_score'] ?? 0, // F1-score model
                    'auc_roc'        => $resultData['auc_roc'] ?? 0, // AUC-ROC score
                    'confusion_matrix' => json_encode($resultData['confusion_matrix'] ?? []), // Confusion matrix
                    'split_ratio'    => $this->split // Split ratio yang digunakan
                ]);

                Log::info("Training result saved with ID: " . $trainingResult->id);

                // Memperbarui prediksi gambar jika ada
                if (!empty($resultData['predictions'])) {
                    $this->updatePredictions($resultData['predictions']);
                    Log::info("Updated " . count($resultData['predictions']) . " predictions");
                }
            });

            Log::info("Training completed successfully");
        } catch (\Exception $e) {
            // Menangani error dan melakukan logging
            Log::error("Training job failed: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            throw $e; // Melemparkan exception kembali untuk penanganan queue
        }
    }

    /**
     * Menghapus model lama dengan split ratio yang sama
     * 
     * @param string $modelDir Direktori model
     * @param int $split Split ratio
     */
    private function deleteOldModel($modelDir, $split)
    {
        try {
            if (File::exists($modelDir)) {
                $modelFile = $modelDir . '/xception_model_' . $split . '.h5';
                
                // Menghapus file model utama
                if (File::exists($modelFile)) {
                    File::delete($modelFile);
                    Log::info("Deleted old model: " . $modelFile);
                }
                
                // Menghapus file model dengan pattern lainnya
                $pattern = $modelDir . '/xception_model_' . $split . '*';
                $files = glob($pattern);
                foreach ($files as $file) {
                    if (File::exists($file)) {
                        File::delete($file);
                        Log::info("Deleted old model file: " . $file);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to delete old model: " . $e->getMessage());
        }
    }

    /**
     * Menghapus kode escape ANSI dari string
     * 
     * @param string $string String yang mungkin mengandung kode ANSI
     * @return string String yang telah dibersihkan
     */
    private function removeAnsiEscapeCodes($string)
    {
        return preg_replace('/\x1b\[[0-9;]*m/', '', $string);
    }

    /**
     * Memperbarui prediksi dan confidence score untuk gambar
     * 
     * @param array $predictions Array hasil prediksi dari model
     */
    private function updatePredictions($predictions)
    {
        $batchSize = 50; // Ukuran batch untuk update database
        foreach (array_chunk($predictions, $batchSize) as $batch) {
            foreach ($batch as $p) {
                if (isset($p['filename']) && isset($p['prediction']) && isset($p['confidence'])) {
                    // Memperbarui prediksi dan confidence untuk setiap gambar
                    Image::where('filename', $p['filename'])
                        ->where('split_ratio', $this->split)
                        ->update([
                            'prediction' => $p['prediction'], // Prediksi (real/fake)
                            'confidence' => $p['confidence'] // Tingkat confidence
                        ]);
                }
            }
            // Membersihkan memory setelah setiap batch
            unset($batch);
            gc_collect_cycles();
        }
    }
}