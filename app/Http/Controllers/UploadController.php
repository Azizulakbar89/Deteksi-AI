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
    // tampilan dashboard
    public function dashboard()
    {
        $trainingResults = TrainingResult::orderBy('created_at', 'desc')->get();
        $totalModels = TrainingResult::count();
        $totalImages = Image::count();

        // tampilan akurasi tertinggi
        $bestAccuracy = TrainingResult::max('accuracy');
        $bestAccuracyModel = TrainingResult::where('accuracy', $bestAccuracy)->first();
        $bestAccuracyRatio = $bestAccuracyModel ? $bestAccuracyModel->split_ratio : null;

        $latestTraining = TrainingResult::latest()->first();

        return view('home', compact(
            'trainingResults',
            'totalModels',
            'totalImages',
            'bestAccuracy',
            'bestAccuracyRatio',
            'latestTraining'
        ));
    }

    // upload dataset
    public function upload(Request $request)
    {
        set_time_limit(1200);
        ini_set('max_execution_time', 1200);
        ini_set('memory_limit', '2048M');

        $request->validate([
            'zip' => 'required|mimes:zip',
            'split' => 'required|in:90,80,70'
        ]);

        try {
            $split = $request->input('split');

            // hapus data gambar yang splitnya sama dengan yang akan diupload
            $existingImagesCount = Image::where('split_ratio', $split)->count();

            if ($existingImagesCount > 0) {
                // hapus semua data dengan split ratio yang sama
                Log::info("Deleting existing dataset for split ratio: " . $split);
                Image::where('split_ratio', $split)->delete();

                // hapus hasil training dengan split ratio yang sama
                TrainingResult::where('split_ratio', $split)->delete();
            }

            // proses upload file zip
            $zip = $request->file('zip');
            $zipName = 'dataset_' . time() . '.zip';
            $zipPath = $zip->storeAs('private/uploads', $zipName);
            $fullZipPath = Storage::path($zipPath);

            Log::info("Zip file stored at: " . $fullZipPath);

            if (!Storage::exists($zipPath)) {
                throw new \Exception("Zip file not found at: " . $zipPath);
            }

            // eksrtak simpan gambar
            $this->extractAndSaveImages($fullZipPath, $split);

            // hapus zip setelah ekstrak
            Storage::delete($zipPath);

            TrainModelJob::dispatch($split)->onQueue('training');

            return redirect()->route('results')->with('success', 'Tunggu beberapa saat, karena masih dalam proses deteksi gambar AI ');
        } catch (\Exception $e) {
            Log::error("Upload error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    // proses ekstrak simpan gambar
    private function extractAndSaveImages($zipPath, $split)
    {
        $zip = new ZipArchive();
        $extractPath = storage_path('app/uploads/extracted_' . time());

        if ($zip->open($zipPath) !== TRUE) {
            throw new \Exception("Failed to open zip file: " . $zipPath);
        }

        try {
            if (!file_exists($extractPath)) {
                mkdir($extractPath, 0755, true);
            }

            $zip->extractTo($extractPath);
            $zip->close();

            Log::info("Zip extracted to: " . $extractPath);

            $foundFolders = $this->findRealFakeFolders($extractPath);

            if (empty($foundFolders) || !isset($foundFolders['real']) || !isset($foundFolders['fake'])) {
                throw new \Exception("Tidak ditemukan folder 'real' dan 'fake' dalam ZIP file");
            }

            $this->processImagesFromFolders($foundFolders, $split);
            $this->deleteDirectory($extractPath);
        } catch (\Exception $e) {
            $this->deleteDirectory($extractPath);
            throw $e;
        }
    }

    // proses pencarian folder real dan fake
    private function findRealFakeFolders($basePath)
    {
        $folders = [];
        $items = scandir($basePath);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $itemPath = $basePath . '/' . $item;
            if (is_dir($itemPath)) {
                $lowerItem = strtolower($item);

                if (str_contains($lowerItem, 'real')) {
                    $folders['real'] = $itemPath;
                } elseif (str_contains($lowerItem, 'fake')) {
                    $folders['fake'] = $itemPath;
                }

                // mencari kedalam sub folder
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


    private function processImagesFromFolders($folders, $split)
    {
        $trainRatio = $split / 100;

        foreach ($folders as $type => $folderPath) {
            if (!file_exists($folderPath)) {
                Log::warning("Folder not found: " . $folderPath);
                continue;
            }

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

            shuffle($imageFiles);
            $splitPoint = (int)($trainRatio * count($imageFiles));
            $trainImages = array_slice($imageFiles, 0, $splitPoint);
            $testImages = array_slice($imageFiles, $splitPoint);

            $this->processImageBatch($folderPath, $trainImages, $type, 'train', $split);
            $this->processImageBatch($folderPath, $testImages, $type, 'test', $split);

            Log::info("Processed {$type}: " . count($trainImages) . " train, " . count($testImages) . " test");
        }
    }

    private function processImageBatch($folderPath, $images, $type, $splitType, $splitRatio)
    {
        $batchSize = 30;
        $batches = array_chunk($images, $batchSize);

        foreach ($batches as $batch) {
            $data = [];
            foreach ($batch as $image) {
                $imagePath = $folderPath . '/' . $image;

                if (!file_exists($imagePath)) {
                    Log::warning("Image file not found during processing: " . $imagePath);
                    continue;
                }

                $sanitizedImageName = preg_replace('/[^A-Za-z0-9._-]/', '_', $image);
                $filename = $type . '_' . $sanitizedImageName;

                $storageName = $sanitizedImageName;

                $storagePath = "images/{$splitRatio}/{$splitType}/{$type}/" . $storageName;

                $contents = file_get_contents($imagePath);
                if ($contents === false) {
                    Log::error("Failed to read image: " . $imagePath);
                    continue;
                }

                $directory = dirname(Storage::disk('public')->path($storagePath));
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }

                $saved = Storage::disk('public')->put($storagePath, $contents);
                if (!$saved) {
                    Log::error("Failed to save image to: " . $storagePath);
                    continue;
                }

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

            if (!empty($data)) {
                Image::insert($data);
                unset($data);
                gc_collect_cycles();
            }
        }
    }

    private function isImageFile($filePath)
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, $allowedExtensions);
    }

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

    public function results()
    {
        $results = TrainingResult::latest()->first();

        $matrix90 = TrainingResult::where('split_ratio', 90)->latest()->first();
        $matrix80 = TrainingResult::where('split_ratio', 80)->latest()->first();
        $matrix70 = TrainingResult::where('split_ratio', 70)->latest()->first();

        $latestSplitRatio = $results ? $results->split_ratio : null;
        $images = $latestSplitRatio
            ? Image::where('split_ratio', $latestSplitRatio)->whereNotNull('prediction')->paginate(50)
            : Image::whereNotNull('prediction')->paginate(50);

        return view('results', compact('results', 'images', 'matrix90', 'matrix80', 'matrix70'));
    }
}
