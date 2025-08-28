<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CNN Xception AI Detector</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        .upload-card {
            border: 2px dashed #dee2e6;
            transition: all 0.3s;
        }

        .upload-card:hover {
            border-color: #667eea;
            background-color: #f8f9fa;
        }

        .btn-upload {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            transition: all 0.3s;
        }

        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }

        .feature-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #667eea;
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
                        <a class="nav-link {{ request()->routeIs('results') ? 'active' : '' }}"
                            href="{{ route('results') }}">
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
                            <h2 class="fw-bold text-primary">AI Image Detector</h2>
                            <p class="text-muted">Upload dataset </p>
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

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <!-- Upload Cards -->
                <div class="row">
                    <!-- Train Model Card -->
                    <div class="col-lg-12 mb-4">
                        <div class="card shadow-sm card-hover h-100">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="fas fa-robot me-2"></i>Train Model with Dataset</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <i class="fas fa-file-archive feature-icon"></i>
                                    <h5>Upload Dataset ZIP</h5>
                                    <p class="text-muted">Dataset harus berisi folder 'real' dan 'fake'</p>
                                </div>

                                <form action="{{ route('upload.process') }}" method="POST"
                                    enctype="multipart/form-data">
                                    @csrf
                                    <div class="mb-3">
                                        <label for="zip" class="form-label">Dataset ZIP File:</label>
                                        <input type="file" class="form-control" name="zip" accept=".zip"
                                            required>
                                        <div class="form-text">Format ZIP yang berisi folder real dan fake</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="split" class="form-label">Train/Test Split Ratio:</label>
                                        <select name="split" class="form-select">
                                            <option value="90">90% Train / 10% Test</option>
                                            <option value="80" selected>80% Train / 20% Test</option>
                                            <option value="70">70% Train / 30% Test</option>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-upload w-100">
                                        <i class="fas fa-cloud-upload-alt me-2"></i>Upload & Train Model
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Features Section -->
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>How It Works</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="text-center">
                                            <i class="fas fa-database feature-icon"></i>
                                            <h6>1. Upload Dataset</h6>
                                            <p class="text-muted small">Upload ZIP file berisi gambar real dan fake
                                                untuk training model</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="text-center">
                                            <i class="fas fa-cogs feature-icon"></i>
                                            <h6>2. Model Training</h6>
                                            <p class="text-muted small">Sistem akan melatih model CNN Xception dengan
                                                dataset yang diupload</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="text-center">
                                            <i class="fas fa-chart-line feature-icon"></i>
                                            <h6>3. View Results</h6>
                                            <p class="text-muted small">Lihat hasil training dan lakukan prediksi pada
                                                gambar baru</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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

        document.querySelector('input[name="zip"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && !file.name.endsWith('.zip')) {
                alert('Please select a ZIP file');
                e.target.value = '';
            }
        });

        document.querySelector('input[name="image"]').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                if (!validTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPEG, PNG, JPG)');
                    e.target.value = '';
                }

                if (file.size > 2 * 1024 * 1024) {
                    alert('File size must be less than 2MB');
                    e.target.value = '';
                }
            }
        });
    </script>
</body>

</html>
