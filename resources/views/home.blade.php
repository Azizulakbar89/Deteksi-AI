<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CNN Xception AI Detector - Dashboard</title>
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

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .metric-description small {
            font-family: monospace;
            background-color: #f8f9fa;
            padding: 2px 5px;
            border-radius: 3px;
        }

        .ratio-badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.65rem;
        }

        .performance-card {
            border-left: 4px solid;
            transition: all 0.3s;
        }

        .performance-card:hover {
            transform: translateY(-3px);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: bold;
        }

        .comparison-chart {
            height: 250px;
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
        
        .split-ratio-display {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <div class="sidebar fixed-top" style="width: 250px;">
            <div class="p-3">
                <h4 class="text-white text-center mb-4">
                    <i class="fas fa-brain"></i> CNN AI Detector
                </h4>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('home') }}">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('upload.page') }}">
                            <i class="fas fa-upload"></i> Upload Dataset
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('results') }}">
                            <i class="fas fa-chart-bar"></i> Hasil Prediksi
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="main-content">
            <div class="container-fluid py-4">
                <div class="row mb-4">
                    <div class="col">
                        <div class="page-header">
                            <h2 class="fw-bold text-primary">Dashboard</h2>
                            <p class="text-muted">Tampilan dashboard perbandingan ratio dataset gambar AI</p>
                            <hr class="border-primary opacity-50">
                        </div>
                    </div>
                </div>

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card border-success card-hover h-100">
                            <div class="card-body text-center">
                                <div class="icon-circle bg-success mx-auto mb-3">
                                    <i class="fas fa-bullseye text-white"></i>
                                </div>
                                <h5 class="card-title">Akurasi Tertinggi</h5>
                                <h3 class="text-success fw-bold">{{ number_format($bestAccuracy * 100, 2) }}%</h3>
                                <p class="text-muted">Split Ratio: {{ $bestAccuracyRatio }}%</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card border-danger card-hover h-100">
                            <div class="card-body text-center">
                                <div class="icon-circle bg-danger mx-auto mb-3">
                                    <i class="fas fa-chart-line text-white"></i>
                                </div>
                                <h5 class="card-title">F1-Score Tertinggi</h5>
                                <h3 class="text-danger fw-bold">{{ number_format($bestF1Score * 100, 2) }}%</h3>
                                <p class="text-muted">Split Ratio: {{ $bestF1ScoreRatio }}%</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card border-primary card-hover h-100">
                            <div class="card-body text-center">
                                <div class="icon-circle bg-primary mx-auto mb-3">
                                    <i class="fas fa-chart-area text-white"></i>
                                </div>
                                <h5 class="card-title">AUC-ROC Tertinggi</h5>
                                <h3 class="text-primary fw-bold">{{ number_format($bestAucRoc * 100, 2) }}%</h3>
                                <p class="text-muted">Split Ratio: {{ $bestAucRocRatio }}%</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card performance-card border-start-success card-hover h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Banyaknya Prediksi</h6>
                                        <h3 class="stats-number text-success mb-0">
                                            {{ $totalModels }}
                                        </h3>
                                    </div>
                                    <div class="icon-circle bg-success">
                                        <i class="fas fa-robot text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card performance-card border-start-info card-hover h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Akurasi Terbaik</h6>
                                        <h3 class="stats-number text-info mb-0">
                                            {{ $bestAccuracy ? number_format($bestAccuracy * 100, 2) . '%' : 'N/A' }}
                                        </h3>
                                        @if ($bestAccuracyRatio)
                                            <span class="badge ratio-badge bg-info">{{ $bestAccuracyRatio }}%
                                                Split</span>
                                        @endif
                                    </div>
                                    <div class="icon-circle bg-info">
                                        <i class="fas fa-chart-line text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card performance-card border-start-warning card-hover h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Total Gambar</h6>
                                        <h3 class="stats-number text-warning mb-0">
                                            {{ $totalImages }}
                                        </h3>
                                    </div>
                                    <div class="icon-circle bg-warning">
                                        <i class="fas fa-database text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card performance-card border-start-primary card-hover h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Latest Training</h6>
                                        <h3 class="stats-number text-primary mb-0">
                                            @if ($latestTraining)
                                                {{ $latestTraining->split_ratio }}% Split
                                            @else
                                                N/A
                                            @endif
                                        </h3>
                                    </div>
                                    <div class="icon-circle bg-primary">
                                        <i class="fas fa-clock text-white"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm card-hover">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Grafik Komparasi Antar Ratio
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="performanceComparisonChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow-sm card-hover">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-table me-2"></i>Ratio prediksi terakhir</h5>
                                <span class="badge bg-primary">Total: {{ $trainingResults->count() }}</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Split Ratio</th>
                                                <th>Accuracy</th>
                                                <th>Precision</th>
                                                <th>Recall</th>
                                                <th>F1-Score</th>
                                                <th>AUC-ROC</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($trainingResults as $result)
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            {{ $result->split_ratio }}% Train /
                                                            {{ 100 - $result->split_ratio }}% Test
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="fw-bold text-success">
                                                            {{ number_format($result->accuracy * 100, 2) }}%
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="fw-bold text-info">
                                                            {{ number_format($result->precision * 100, 2) }}%
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="fw-bold text-warning">
                                                            {{ number_format($result->recall * 100, 2) }}%
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="fw-bold text-danger">
                                                            {{ number_format($result->f1_score * 100, 2) }}%
                                                        </span>
                                                        <div class="split-ratio-display">
                                                            Ratio: {{ $result->split_ratio }}%
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="fw-bold text-primary">
                                                            {{ number_format($result->auc_roc * 100, 2) }}%
                                                        </span>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center py-4">
                                                        <div class="empty-state">
                                                            <i class="fas fa-database fa-3x text-muted mb-3"></i>
                                                            <h5>No training results found</h5>
                                                            <p class="text-muted">Upload a dataset to see training
                                                                results</p>
                                                            <a href="{{ route('upload.page') }}"
                                                                class="btn btn-primary mt-2">
                                                                <i class="fas fa-upload me-1"></i>Upload Dataset
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    @foreach ([90, 80, 70] as $ratio)
                        <div class="col-md-4 mb-4">
                            <div class="card shadow-sm card-hover h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">
                                        <i class="fas fa-chart-pie me-2"></i>{{ $ratio }}% Split Performance
                                    </h6>
                                </div>
                                <div class="card-body">
                                    @php
                                        $ratioData = $trainingResults->where('split_ratio', $ratio)->first();
                                    @endphp
                                    @if ($ratioData)
                                        <div class="text-center mb-3">
                                            <div class="comparison-chart">
                                                <canvas id="ratioChart{{ $ratio }}"></canvas>
                                            </div>
                                        </div>
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <small class="text-muted">Accuracy</small>
                                                <div class="fw-bold text-success">
                                                    {{ number_format($ratioData->accuracy * 100, 2) }}%
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">F1-Score</small>
                                                <div class="fw-bold text-danger">
                                                    {{ number_format($ratioData->f1_score * 100, 2) }}%
                                                </div>
                                                <div class="split-ratio-display">
                                                    Ratio: {{ $ratio }}%
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row text-center mt-2">
                                            <div class="col-6">
                                                <small class="text-muted">AUC-ROC</small>
                                                <div class="fw-bold text-primary">
                                                    {{ number_format($ratioData->auc_roc * 100, 2) }}%
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Status</small>
                                                <div>
                                                    @if ($ratioData->f1_score >= 0.90 && $ratioData->auc_roc >= 0.95)
                                                        <span class="badge bg-success">Berhasil</span>
                                                    @else
                                                        <span class="badge bg-danger">Belum Berhasil</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="text-center py-4">
                                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">No data for {{ $ratio }}% split</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const performanceCtx = document.getElementById('performanceComparisonChart').getContext('2d');
            const ratios = {!! json_encode($trainingResults->pluck('split_ratio')) !!};
            const accuracies = {!! json_encode($trainingResults->pluck('accuracy')) !!};
            const precisions = {!! json_encode($trainingResults->pluck('precision')) !!};
            const recalls = {!! json_encode($trainingResults->pluck('recall')) !!};
            const f1Scores = {!! json_encode($trainingResults->pluck('f1_score')) !!};
            const aucRocs = {!! json_encode($trainingResults->pluck('auc_roc')) !!};

            new Chart(performanceCtx, {
                type: 'line',
                data: {
                    labels: ratios.map(r => r + '%'),
                    datasets: [{
                            label: 'Accuracy',
                            data: accuracies.map(a => a * 100),
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Precision',
                            data: precisions.map(p => p * 100),
                            borderColor: '#17a2b8',
                            backgroundColor: 'rgba(23, 162, 184, 0.1)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Recall',
                            data: recalls.map(r => r * 100),
                            borderColor: '#ffc107',
                            backgroundColor: 'rgba(255, 193, 7, 0.1)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'F1-Score',
                            data: f1Scores.map(f => f * 100),
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'AUC-ROC',
                            data: aucRocs.map(a => a * 100),
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            tension: 0.3,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Percentage (%)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Split Ratio'
                            }
                        }
                    }
                }
            });

            @foreach ([90, 80, 70] as $ratio)
                @php
                    $ratioData = $trainingResults->where('split_ratio', $ratio)->first();
                @endphp
                @if ($ratioData)
                    const ctx{{ $ratio }} = document.getElementById('ratioChart{{ $ratio }}')
                        .getContext('2d');
                    new Chart(ctx{{ $ratio }}, {
                        type: 'doughnut',
                        data: {
                            labels: ['Accuracy', 'Precision', 'Recall', 'F1-Score', 'AUC-ROC'],
                            datasets: [{
                                data: [
                                    {{ $ratioData->accuracy * 100 }},
                                    {{ $ratioData->precision * 100 }},
                                    {{ $ratioData->recall * 100 }},
                                    {{ $ratioData->f1_score * 100 }},
                                    {{ $ratioData->auc_roc * 100 }}
                                ],
                                backgroundColor: [
                                    '#28a745',
                                    '#17a2b8',
                                    '#ffc107',
                                    '#dc3545',
                                    '#007bff'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                @endif
            @endforeach
        });
    </script>
</body>

</html>