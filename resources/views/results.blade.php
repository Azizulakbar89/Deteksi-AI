<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CNN Xception AI Detector</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            margin: 5px 0;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
        }
        .card-hover:hover {
            transform: translateY(-5px);
            transition: all 0.3s;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        .icon-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .confusion-matrix {
            display: grid;
            grid-template-columns: 100px 100px 100px;
            grid-template-rows: 40px 100px 100px;
            gap: 2px;
            margin: 20px 0;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
        }
        .matrix-header {
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        .matrix-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #dee2e6;
            font-size: 18px;
            font-weight: bold;
            border-radius: 4px;
            transition: transform 0.2s;
        }
        .matrix-cell:hover {
            transform: scale(1.05);
        }
        .true-positive {
            background-color: #d4edda;
            color: #155724;
        }
        .false-positive {
            background-color: #f8d7da;
            color: #721c24;
        }
        .false-negative {
            background-color: #f8d7da;
            color: #721c24;
        }
        .true-negative {
            background-color: #d4edda;
            color: #155724;
        }
        .img-thumbnail {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border-radius: 4px;
        }
        .correct {
            background-color: #e6f4ea;
        }
        .incorrect {
            background-color: #f8d7da;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .progress-text {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
            text-shadow: 0 0 2px rgba(0, 0, 0, 0.5);
        }
        .metric-description small {
            font-family: monospace;
            background-color: #f8f9fa;
            padding: 2px 5px;
            border-radius: 3px;
        }
        .empty-state {
            text-align: center;
            padding: 2rem;
        }
        .pagination .page-item .page-link {
            color: #667eea;
            border: 1px solid #dee2e6;
            margin: 0 3px;
            border-radius: 4px;
        }
        .pagination .page-item.active .page-link {
            background-color: #667eea;
            border-color: #667eea;
            color: white;
        }
        .pagination .page-item.disabled .page-link {
            color: #6c757d;
        }
        .pagination .page-item:not(.disabled):not(.active) .page-link:hover {
            background-color: #f1f5ff;
        }
        .matrix-comparison {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
        }
        .matrix-card {
            flex: 1;
            min-width: 300px;
            max-width: 400px;
        }
        .no-data-message {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        .no-data-message i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .no-image-placeholder {
            width: 80px;
            height: 80px;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            color: #6c757d;
        }
        .success-criteria {
            border-left: 4px solid;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .criteria-met {
            border-left-color: #28a745;
            background-color: #f8fff9;
        }
        .criteria-not-met {
            border-left-color: #dc3545;
            background-color: #fff8f8;
        }
        .metric-badge {
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar fixed-top" style="width: 250px;">
            <div class="p-3">
                <h4 class="text-white text-center mb-4">
                    <i class="fas fa-brain"></i> CNN AI Detector
                </h4>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('upload.*') ? 'active' : '' }}"
                            href="{{ route('upload.page') }}">
                            <i class="fas fa-upload"></i> Upload Dataset
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('results') }}">
                            <i class="fas fa-chart-bar"></i> Hasil Prediksi
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <!-- Main Content -->
        <div class="main-content">
            <div class="container-fluid py-4">
                <!-- Page Header -->
                <div class="row mb-4">
                    <div class="col">
                        <div class="page-header">
                            <h2 class="fw-bold text-primary">Hasil Prediksi</h2>
                            <p class="text-muted">Detail Klasifikasi Hasil Prediksi</p>
                            <hr class="border-primary opacity-50">
                        </div>
                    </div>
                </div>
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if ($results)
                    <!-- Success Criteria Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card shadow-sm card-hover">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Kriteria Keberhasilan Model</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="success-criteria {{ $successCriteria['f1_score']['achieved'] ? 'criteria-met' : 'criteria-not-met' }}">
                                                <h6 class="d-flex justify-content-between align-items-center">
                                                    <span>F1-Score ≥ 0.90</span>
                                                    <span class="badge {{ $successCriteria['f1_score']['achieved'] ? 'bg-success' : 'bg-danger' }} metric-badge">
                                                        {{ $successCriteria['f1_score']['achieved'] ? 'Tercapai' : 'Tidak Tercapai' }}
                                                    </span>
                                                </h6>
                                                <p class="mb-1">Nilai saat ini: <strong>{{ number_format($successCriteria['f1_score']['value'], 4) }}</strong></p>
                                                <div class="progress mt-2" style="height: 20px;">
                                                    <div class="progress-bar {{ $successCriteria['f1_score']['achieved'] ? 'bg-success' : 'bg-danger' }}"
                                                         role="progressbar"
                                                         style="width: {{ min($successCriteria['f1_score']['value'] * 100, 100) }}%"
                                                         aria-valuenow="{{ $successCriteria['f1_score']['value'] * 100 }}"
                                                         aria-valuemin="0"
                                                         aria-valuemax="100">
                                                        {{ number_format($successCriteria['f1_score']['value'] * 100, 1) }}%
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="success-criteria {{ $successCriteria['auc_roc']['achieved'] ? 'criteria-met' : 'criteria-not-met' }}">
                                                <h6 class="d-flex justify-content-between align-items-center">
                                                    <span>AUC-ROC ≥ 0.95</span>
                                                    <span class="badge {{ $successCriteria['auc_roc']['achieved'] ? 'bg-success' : 'bg-danger' }} metric-badge">
                                                        {{ $successCriteria['auc_roc']['achieved'] ? 'Tercapai' : 'Tidak Tercapai' }}
                                                    </span>
                                                </h6>
                                                <p class="mb-1">Nilai saat ini: <strong>{{ number_format($successCriteria['auc_roc']['value'], 4) }}</strong></p>
                                                <div class="progress mt-2" style="height: 20px;">
                                                    <div class="progress-bar {{ $successCriteria['auc_roc']['achieved'] ? 'bg-success' : 'bg-danger' }}"
                                                         role="progressbar"
                                                         style="width: {{ min($successCriteria['auc_roc']['value'] * 100, 100) }}%"
                                                         aria-valuenow="{{ $successCriteria['auc_roc']['value'] * 100 }}"
                                                         aria-valuemin="0"
                                                         aria-valuemax="100">
                                                        {{ number_format($successCriteria['auc_roc']['value'] * 100, 1) }}%
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Metrics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card border-start border-success border-4 card-hover h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Accuracy</h6>
                                    <h3 class="card-title fw-bold text-success mb-3">
                                        {{ number_format($results->accuracy * 100, 2) }}%
                                    </h3>
                                    <div class="metric-description">
                                        <small class="text-muted">(TP + TN) / Total</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card border-start border-info border-4 card-hover h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Precision</h6>
                                    <h3 class="card-title fw-bold text-info mb-3">
                                        {{ number_format($results->precision * 100, 2) }}%
                                    </h3>
                                    <div class="metric-description">
                                        <small class="text-muted">TP / (TP + FP)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card border-start border-warning border-4 card-hover h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Recall (Sensitivity)</h6>
                                    <h3 class="card-title fw-bold text-warning mb-3">
                                        {{ number_format($results->recall * 100, 2) }}%
                                    </h3>
                                    <div class="metric-description">
                                        <small class="text-muted">TP / (TP + FN)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card border-start border-primary border-4 card-hover h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">AUC-ROC</h6>
                                    <h3 class="card-title fw-bold text-primary mb-3">
                                        {{ number_format($results->auc_roc * 100, 2) }}%
                                    </h3>
                                    <div class="metric-description">
                                        <small class="text-muted">Area Under ROC Curve</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card border-start border-danger border-4 card-hover h-100">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">F1-Score</h6>
                                    <h3 class="card-title fw-bold text-danger mb-3">
                                        {{ number_format($results->f1_score * 100, 2) }}%
                                    </h3>
                                    <div class="metric-description">
                                        <small class="text-muted">2 × (Precision × Recall) / (Precision + Recall)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Confusion Matrix Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card shadow-sm card-hover">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="fas fa-table me-2"></i>Confusion Matrix - Split Ratio:
                                        {{ $results->split_ratio }}% Train / {{ 100 - $results->split_ratio }}% Test
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <!-- Confusion Matrix Table -->
                                        <div class="col-md-6 mb-3 mb-md-0">
                                            @php
                                                $confusion_matrix = json_decode($results->confusion_matrix, true);
                                            @endphp
                                            <div class="confusion-matrix">
                                                <div class="matrix-header"></div>
                                                <div class="matrix-header">Predicted Real</div>
                                                <div class="matrix-header">Predicted Fake</div>
                                                <div class="matrix-header">Actual Real</div>
                                                <div class="matrix-cell true-positive">{{ $confusion_matrix[0][0] }} (TN)</div>
                                                <div class="matrix-cell false-negative">{{ $confusion_matrix[0][1] }} (FP)</div>
                                                <div class="matrix-header">Actual Fake</div>
                                                <div class="matrix-cell false-positive">{{ $confusion_matrix[1][0] }} (FN)</div>
                                                <div class="matrix-cell true-negative">{{ $confusion_matrix[1][1] }} (TP)</div>
                                            </div>
                                            <div class="mt-3">
                                                <p><strong>Keterangan:</strong></p>
                                                <ul class="list-unstyled">
                                                    <li><span class="true-positive p-2 d-inline-block">TN (True Negative)</span>: Real diprediksi sebagai Real</li>
                                                    <li><span class="false-negative p-2 d-inline-block">FP (False Positive)</span>: Real diprediksi sebagai Fake</li>
                                                    <li><span class="false-positive p-2 d-inline-block">FN (False Negative)</span>: Fake diprediksi sebagai Real</li>
                                                    <li><span class="true-negative p-2 d-inline-block">TP (True Positive)</span>: Fake diprediksi sebagai Fake</li>
                                                </ul>
                                            </div>
                                        </div>
                                        <!-- Chart -->
                                        <div class="col-md-6">
                                            <div class="chart-container">
                                                <canvas id="confusionMatrixChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Prediction Results Table -->
                    <div class="row">
                        <div class="col">
                            <div class="card shadow-sm card-hover">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="fas fa-list-alt me-2"></i>Uploaded Images with Predictions</h5>
                                    <span class="badge bg-primary">Total: {{ $images->total() }}</span>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80px">Preview</th>
                                                    <th>Filename</th>
                                                    <th>Actual Type</th>
                                                    <th>Prediction</th>
                                                    <th>Confidence</th>
                                                    <th>Split</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($images as $image)
                                                    @php
                                                        $isCorrect = $image->prediction && $image->type === $image->prediction;
                                                        $imagePath = 'storage/' . $image->path;
                                                        $fileExists = file_exists(public_path($imagePath)) || Storage::disk()->exists($image->path);
                                                    @endphp
                                                    <tr class="{{ $isCorrect ? 'correct' : ($image->prediction ? 'incorrect' : '') }}">
                                                        <td>
                                                            @if ($fileExists)
                                                                @php
                                                                    $imageUrl = Storage::disk()->exists($image->path)
                                                                        ? Storage::disk()->url($image->path)
                                                                        : asset($imagePath);
                                                                @endphp
                                                                <img src="{{ $imageUrl }}" class="img-thumbnail"
                                                                     alt="{{ $image->filename }}"
                                                                     style="max-width: 80px; max-height: 80px;"
                                                                     onclick="showImageModal('{{ $imageUrl }}')">
                                                            @else
                                                                <div class="no-image-placeholder">
                                                                    <i class="fas fa-image"></i>
                                                                </div>
                                                            @endif
                                                        </td>
                                                        <td class="align-middle">
                                                            <span data-bs-toggle="tooltip" title="{{ $image->filename }}">
                                                                {{ Str::limit($image->filename, 25) }}
                                                            </span>
                                                        </td>
                                                        <td class="align-middle">
                                                            <span class="badge rounded-pill bg-{{ $image->type == 'real' ? 'success' : 'danger' }}">
                                                                {{ ucfirst($image->type) }}
                                                            </span>
                                                        </td>
                                                        <td class="align-middle">
                                                            @if ($image->prediction)
                                                                <span class="badge rounded-pill bg-{{ $image->prediction == 'real' ? 'success' : 'danger' }}">
                                                                    {{ ucfirst($image->prediction) }}
                                                                </span>
                                                            @else
                                                                <span class="badge bg-secondary">Not predicted</span>
                                                            @endif
                                                        </td>
                                                        <td class="align-middle">
                                                            @if ($image->confidence)
                                                                <div class="progress" style="height: 20px;">
                                                                    <div class="progress-bar bg-{{ $image->confidence > 0.7 ? 'success' : ($image->confidence > 0.4 ? 'warning' : 'danger') }}"
                                                                         role="progressbar"
                                                                         style="width: {{ $image->confidence * 100 }}%"
                                                                         aria-valuenow="{{ $image->confidence * 100 }}"
                                                                         aria-valuemin="0"
                                                                         aria-valuemax="100">
                                                                        {{ number_format($image->confidence * 100, 1) }}%
                                                                    </div>
                                                                </div>
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                        <td class="align-middle">
                                                            <span class="badge bg-{{ $image->split == 'train' ? 'primary' : 'info' }}">
                                                                {{ ucfirst($image->split) }}
                                                            </span>
                                                        </td>
                                                        <td class="align-middle">
                                                            @if ($image->prediction)
                                                                @if ($isCorrect)
                                                                    <span class="badge bg-success rounded-pill">
                                                                        <i class="fas fa-check me-1"></i>Correct
                                                                    </span>
                                                                @else
                                                                    <span class="badge bg-danger rounded-pill">
                                                                        <i class="fas fa-times me-1"></i>Incorrect
                                                                    </span>
                                                                @endif
                                                            @else
                                                                <span class="badge bg-secondary rounded-pill">N/A</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @if ($images->hasPages())
                                        <div class="d-flex justify-content-center mt-4">
                                            <nav aria-label="Page navigation">
                                                <ul class="pagination">
                                                    @if ($images->onFirstPage())
                                                        <li class="page-item disabled">
                                                            <span class="page-link"><i class="fas fa-angle-left"></i></span>
                                                        </li>
                                                    @else
                                                        <li class="page-item">
                                                            <a class="page-link" href="{{ $images->previousPageUrl() }}" rel="prev">
                                                                <i class="fas fa-angle-left"></i>
                                                            </a>
                                                        </li>
                                                    @endif
                                                    @foreach ($images->getUrlRange(1, $images->lastPage()) as $page => $url)
                                                        @if ($page == $images->currentPage())
                                                            <li class="page-item active">
                                                                <span class="page-link">{{ $page }}</span>
                                                            </li>
                                                        @else
                                                            <li class="page-item">
                                                                <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                                            </li>
                                                        @endif
                                                    @endforeach
                                                    @if ($images->hasMorePages())
                                                        <li class="page-item">
                                                            <a class="page-link" href="{{ $images->nextPageUrl() }}" rel="next">
                                                                <i class="fas fa-angle-right"></i>
                                                            </a>
                                                        </li>
                                                    @else
                                                        <li class="page-item disabled">
                                                            <span class="page-link"><i class="fas fa-angle-right"></i></span>
                                                        </li>
                                                    @endif
                                                </ul>
                                            </nav>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        Belum ada data yang di prediksi pada ratio ini.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Image Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid rounded" alt="Preview">
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        function showImageModal(src) {
            document.getElementById('modalImage').src = src;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }
        document.addEventListener('DOMContentLoaded', function() {
            @if ($results)
                const ctx = document.getElementById('confusionMatrixChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['True Negative', 'False Positive', 'False Negative', 'True Positive'],
                        datasets: [{
                            label: 'Confusion Matrix',
                            data: [
                                {{ $confusion_matrix[0][0] }},
                                {{ $confusion_matrix[0][1] }},
                                {{ $confusion_matrix[1][0] }},
                                {{ $confusion_matrix[1][1] }}
                            ],
                            backgroundColor: [
                                '#d4edda',
                                '#f8d7da',
                                '#f8d7da',
                                '#d4edda'
                            ],
                            borderColor: [
                                '#155724',
                                '#721c24',
                                '#721c24',
                                '#155724'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Confusion Matrix Breakdown',
                                font: {
                                    size: 16
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Count'
                                }
                            }
                        }
                    }
                });
            @endif
        });
    </script>
</body>
</html>