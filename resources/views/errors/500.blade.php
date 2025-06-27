<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Server Error - HERMES Koperasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .error-container {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 600px;
            margin: 2rem;
        }

        .error-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }

        .error-code {
            font-size: 4rem;
            font-weight: bold;
            color: #dc3545;
            margin-bottom: 1rem;
        }

        .error-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
        }

        .error-message {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-custom {
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            color: white;
        }

        .btn-outline-custom {
            border: 2px solid #667eea;
            color: #667eea;
            background: transparent;
        }

        .btn-outline-custom:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .error-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 2rem;
            text-align: left;
            font-family: monospace;
            font-size: 0.9rem;
            color: #666;
        }

        .support-info {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
            color: #888;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="bi bi-exclamation-triangle"></i>
        </div>

        <div class="error-code">500</div>

        <h1 class="error-title">Terjadi Kesalahan Server</h1>

        <p class="error-message">
            Maaf, terjadi kesalahan internal pada server. Tim teknis kami telah diberitahu
            dan sedang menangani masalah ini. Silakan coba lagi dalam beberapa menit.
        </p>

        <div class="action-buttons">
            <a href="{{ route('dashboard') }}" class="btn btn-custom btn-primary-custom">
                <i class="bi bi-house me-2"></i>
                Kembali ke Dashboard
            </a>
            <button onclick="window.location.reload()" class="btn btn-custom btn-outline-custom">
                <i class="bi bi-arrow-clockwise me-2"></i>
                Coba Lagi
            </button>
            <a href="javascript:history.back()" class="btn btn-custom btn-outline-custom">
                <i class="bi bi-arrow-left me-2"></i>
                Halaman Sebelumnya
            </a>
        </div>

        @if(config('app.debug'))
        <div class="error-details">
            <strong>Error Details (Debug Mode):</strong><br>
            @if(isset($exception))
            <strong>Message:</strong> {{ $exception->getMessage() }}<br>
            <strong>File:</strong> {{ $exception->getFile() }}<br>
            <strong>Line:</strong> {{ $exception->getLine() }}<br>
            <strong>Time:</strong> {{ now()->format('Y-m-d H:i:s') }}
            @endif
        </div>
        @endif

        <div class="support-info">
            <p>
                <strong>Butuh bantuan?</strong><br>
                Hubungi tim support di <a href="mailto:support@hermes-koperasi.com">support@hermes-koperasi.com</a><br>
                atau telepon <a href="tel:+62-xxx-xxxx-xxxx">+62-xxx-xxxx-xxxx</a>
            </p>
            <p>
                <small>Error ID: {{ Str::uuid() }} | {{ now()->format('Y-m-d H:i:s') }}</small>
            </p>
        </div>
    </div>

    <script>
        // Auto-retry after 30 seconds
        setTimeout(() => {
            if (confirm('Mencoba memuat ulang halaman secara otomatis. Lanjutkan?')) {
                window.location.reload();
            }
        }, 30000);

        // Send error report
        if (navigator.onLine) {
            fetch('/api/error-report', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    error_type: '500',
                    url: window.location.href,
                    user_agent: navigator.userAgent,
                    timestamp: new Date().toISOString()
                })
            }).catch(console.error);
        }
    </script>
</body>

</html>
