<?php

namespace App\Http\Controllers;

use ZipArchive;
use App\Models\Image;
use App\Jobs\TrainModelJob;
use Illuminate\Http\Request;
use App\Models\TrainingResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;

class UploadController extends Controller
{
    /**
     * Menampilkan dashboard dengan statistik training
     * 
     * Method ini mengambil data hasil training, jumlah model, jumlah gambar,
     * dan metrik terbaik (akurasi, F1-score, AUC-ROC) untuk ditampilkan di dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function dashboard()
    {
        // Mengambil semua hasil training secara descending
        $trainingResults = TrainingResult::orderBy('created_at', 'desc')->get();
        
        // Menghitung total model dan gambar
        $totalModels = TrainingResult::count();
        $totalImages = Image::count();

        // Mencari akurasi tertinggi
        $bestAccuracy = TrainingResult::max('accuracy');
        $bestAccuracyModel = TrainingResult::where('accuracy', $bestAccuracy)->first();
        $bestAccuracyRatio = $bestAccuracyModel ? $bestAccuracyModel->split_ratio : null;

        // Mencari F1-score tertinggi
        $bestF1Score = TrainingResult::max('f1_score');
        $bestF1ScoreModel = TrainingResult::where('f1_score', $bestF1Score)->first();
        $bestF1ScoreRatio = $bestF1ScoreModel ? $bestF1ScoreModel->split_ratio : null;

        // Mencari AUC-ROC tertinggi
        $bestAucRoc = TrainingResult::max('auc_roc');
        $bestAucRocModel = TrainingResult::where('auc_roc', $bestAucRoc)->first();
        $bestAucRocRatio = $bestAucRocModel ? $bestAucRocModel->split_ratio : null;

        // Mengambil training terbaru
        $latestTraining = TrainingResult::latest()->first();

        // Mengembalikan view dengan semua data
        return view('home', compact(
            'trainingResults',
            'totalModels',
            'totalImages',
            'bestAccuracy',
            'bestAccuracyRatio',
            'bestF1Score',
            'bestF1ScoreRatio',
            'bestAucRoc',
            'bestAucRocRatio',
            'latestTraining'
        ));
    }

    /**
     * Memproses upload file zip dataset
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function upload(Request $request)
    {
        // Menetapkan batas waktu eksekusi yang lebih lama
        set_time_limit(1200);
        ini_set('max_execution_time', 1200); // 20 menit
        ini_set('memory_limit', '2048M'); // 2GB memory

        // Validasi input
        $request->validate([
            'zip' => 'required|mimes:zip', // Hanya menerima file zip
            'split' => 'required|in:90,80,70' // Hanya menerima nilai split 90, 80, atau 70
        ]);

        try {
            $split = $request->input('split');

            // Menghapus data gambar dengan split ratio yang sama jika ada
            $existingImagesCount = Image::where('split_ratio', $split)->count();

            if ($existingImagesCount > 0) {
                Log::info("Deleting existing dataset for split ratio: " . $split);
                Image::where('split_ratio', $split)->delete();
                
                // Juga menghapus hasil training dengan split ratio yang sama
                TrainingResult::where('split_ratio', $split)->delete();
            }

            // Memproses file zip
            $zip = $request->file('zip');
            $zipName = 'dataset_' . time() . '.zip';
            $zipPath = $zip->storeAs('private/uploads', $zipName);
            $fullZipPath = Storage::path($zipPath);

            Log::info("Zip file stored at: " . $fullZipPath);

            // Memastikan file zip tersimpan
            if (!Storage::exists($zipPath)) {
                throw new \Exception("Zip file not found at: " . $zipPath);
            }

            // Mengekstrak dan menyimpan gambar
            $this->extractAndSaveImages($fullZipPath, $split);

            // Menghapus file zip setelah diekstrak
            Storage::delete($zipPath);

            // Menjalankan job training model
            TrainModelJob::dispatch($split)->onQueue('training');

            return redirect()->route('results')->with('success', 'Tunggu beberapa saat, karena masih dalam proses deteksi gambar AI ');
        } catch (\Exception $e) {
            Log::error("Upload error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Mengekstrak file zip dan menyimpan gambar-gambar ke database
     *
     * @param string $zipPath Path ke file zip
     * @param int $split Split ratio (90, 80, atau 70)
     * @throws \Exception
     */
    private function extractAndSaveImages($zipPath, $split)
    {
        $zip = new ZipArchive();
        $extractPath = storage_path('app/uploads/extracted_' . time()); // Path untuk ekstraksi

        if ($zip->open($zipPath) !== TRUE) {
            throw new \Exception("Failed to open zip file: " . $zipPath);
        }

        try {
            // Membuat direktori jika belum ada
            if (!file_exists($extractPath)) {
                mkdir($extractPath, 0755, true);
            }

            // Mengekstrak file zip
            $zip->extractTo($extractPath);
            $zip->close();

            Log::info("Zip extracted to: " . $extractPath);

            // Mencari folder real dan fake
            $foundFolders = $this->findRealFakeFolders($extractPath);

            if (empty($foundFolders) || !isset($foundFolders['real']) || !isset($foundFolders['fake'])) {
                throw new \Exception("Tidak ditemukan folder 'real' dan 'fake' dalam ZIP file");
            }

            // Memproses gambar dari folder
            $this->processImagesFromFolders($foundFolders, $split);
            
            // Menghapus direktori ekstraksi
            $this->deleteDirectory($extractPath);
        } catch (\Exception $e) {
            // Membersihkan direktori ekstraksi jika terjadi error
            $this->deleteDirectory($extractPath);
            throw $e;
        }
    }

    /**
     * Mencari folder real dan fake dalam direktori yang diekstrak
     *
     * @param string $basePath Path dasar untuk pencarian
     * @return array Array dengan path folder real dan fake
     */
    private function findRealFakeFolders($basePath)
    {
        $folders = [];
        $items = scandir($basePath);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $itemPath = $basePath . '/' . $item;
            if (is_dir($itemPath)) {
                $lowerItem = strtolower($item);

                // Mencari folder real
                if (str_contains($lowerItem, 'real')) {
                    $folders['real'] = $itemPath;
                } 
                // Mencari folder fake
                elseif (str_contains($lowerItem, 'fake')) {
                    $folders['fake'] = $itemPath;
                }

                // Mencari di subfolder
                $subItems = scandir($itemPath);
                foreach ($subItems as $subItem) {
                    if ($subItem === '.' || $subItem === '..') continue;

                    $subPath = $itemPath . '/' . $subItem;
                    if (is_dir($subPath)) {
                        $lowerSub = strtolower($subItem);

                        if (str_contains($lowerSub, 'real') && !isset($folders['real'])) {
                            $folders['real'] = $subPath;
                        } elseif (str_contains($lowerSub, 'fake') && !isset($folders['fake'])) {
                            $folders['fake'] = $subPath;
                        }
                    }
                }
            }
        }

        return $folders;
    }

    /**
     * Memproses gambar dari folder real dan fake
     *
     * @param array $folders Array berisi path folder real dan fake
     * @param int $split Split ratio (90, 80, atau 70)
     */
    private function processImagesFromFolders($folders, $split)
    {
        $trainRatio = $split / 100;

        foreach ($folders as $type => $folderPath) {
            if (!file_exists($folderPath)) {
                Log::warning("Folder not found: " . $folderPath);
                continue;
            }

            // Mendapatkan semua file gambar
            $images = array_diff(scandir($folderPath), ['.', '..']);
            $imageFiles = [];

            foreach ($images as $image) {
                $imagePath = $folderPath . '/' . $image;
                if (is_file($imagePath) && $this->isImageFile($imagePath)) {
                    $imageFiles[] = $image;
                }
            }

            if (empty($imageFiles)) {
                Log::warning("No images found in folder: " . $folderPath);
                continue;
            }

            // Membagi data menjadi train dan test
            shuffle($imageFiles);
            $splitPoint = (int)($trainRatio * count($imageFiles));
            $trainImages = array_slice($imageFiles, 0, $splitPoint);
            $testImages = array_slice($imageFiles, $splitPoint);

            // Memproses batch gambar
            $this->processImageBatch($folderPath, $trainImages, $type, 'train', $split);
            $this->processImageBatch($folderPath, $testImages, $type, 'test', $split);

            Log::info("Processed {$type}: " . count($trainImages) . " train, " . count($testImages) . " test");
        }
    }

    /**
     * Memproses batch gambar dan menyimpannya ke database
     *
     * @param string $folderPath Path ke folder gambar
     * @param array $images Array nama file gambar
     * @param string $type Tipe gambar (real/fake)
     * @param string $splitType Tipe split (train/test)
     * @param int $splitRatio Split ratio (90, 80, atau 70)
     */
    private function processImageBatch($folderPath, $images, $type, $splitType, $splitRatio)
    {
        $batchSize = 30; // Ukuran batch untuk processing
        $batches = array_chunk($images, $batchSize);

        foreach ($batches as $batch) {
            $data = [];
            foreach ($batch as $image) {
                $imagePath = $folderPath . '/' . $image;

                if (!file_exists($imagePath)) {
                    Log::warning("Image file not found during processing: " . $imagePath);
                    continue;
                }

                // Membersihkan nama file
                $sanitizedImageName = preg_replace('/[^A-Za-z0-9._-]/', '_', $image);
                $filename = $type . '_' . $sanitizedImageName;
                $storageName = $sanitizedImageName;

                // Menentukan path penyimpanan
                $storagePath = "images/{$splitRatio}/{$splitType}/{$type}/" . $storageName;

                // Membaca dan menyimpan gambar
                $contents = file_get_contents($imagePath);
                if ($contents === false) {
                    Log::error("Failed to read image: " . $imagePath);
                    continue;
                }

                // Membuat direktori jika belum ada
                $directory = dirname(Storage::disk('public')->path($storagePath));
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }

                // Menyimpan gambar
                $saved = Storage::disk('public')->put($storagePath, $contents);
                if (!$saved) {
                    Log::error("Failed to save image to: " . $storagePath);
                    continue;
                }

                // Menyiapkan data untuk disimpan ke database
                $data[] = [
                    'filename' => $filename,
                    'path' => $storagePath,
                    'type' => $type,
                    'split' => $splitType,
                    'split_ratio' => $splitRatio,
                    'prediction' => null,
                    'confidence' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            // Menyimpan data ke database jika ada
            if (!empty($data)) {
                Image::insert($data);
                unset($data);
                gc_collect_cycles(); // Membersihkan memory
            }
        }
    }

    /**
     * Memeriksa apakah file adalah gambar
     *
     * @param string $filePath Path ke file
     * @return bool True jika file adalah gambar, false jika tidak
     */
    private function isImageFile($filePath)
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, $allowedExtensions);
    }

    /**
     * Menghapus direktori dan isinya secara rekursif
     *
     * @param string $dir Path ke direktori
     * @return bool True jika berhasil, false jika gagal
     */
    private function deleteDirectory($dir)
    {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }

    /**
     * Menampilkan hasil training
     *
     * @return \Illuminate\View\View
     */
    public function results()
    {
        // Mengambil hasil training terbaru
        $results = TrainingResult::latest()->first();

        // Mengambil hasil training berdasarkan split ratio
        $matrix90 = TrainingResult::where('split_ratio', 90)->latest()->first();
        $matrix80 = TrainingResult::where('split_ratio', 80)->latest()->first();
        $matrix70 = TrainingResult::where('split_ratio', 70)->latest()->first();

        // Mengambil gambar dengan prediksi
        $latestSplitRatio = $results ? $results->split_ratio : null;
        $images = $latestSplitRatio
            ? Image::where('split_ratio', $latestSplitRatio)->whereNotNull('prediction')->paginate(50)
            : Image::whereNotNull('prediction')->paginate(50);

        // Kriteria keberhasilan model
        $successCriteria = [
            'f1_score' => [
                'target' => 0.90,
                'achieved' => $results ? $results->f1_score >= 0.90 : false,
                'value' => $results ? $results->f1_score : 0
            ],
            'auc_roc' => [
                'target' => 0.95,
                'achieved' => $results ? $results->auc_roc >= 0.95 : false,
                'value' => $results ? $results->auc_roc : 0
            ]
        ];

        return view('results', compact('results', 'images', 'matrix90', 'matrix80', 'matrix70', 'successCriteria'));
    }
}