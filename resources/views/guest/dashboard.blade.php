{{-- resources/views/guest/dashboard.blade.php --}}
@extends('layouts.guest-landing')

@section('title', 'Beranda')

@section('content')
<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-6" data-aos="fade-right">
                <div class="hero-content">
                    <h1 class="hero-title">
                        Sistem Informasi
                        <span class="text-gradient">Manajemen Koperasi</span>
                        Terdepan
                    </h1>
                    <p class="hero-subtitle">
                        SIMKOP hadir untuk membantu koperasi di Kabupaten Muara Enim dalam mengelola
                        laporan keuangan dengan mudah, akurat, dan sesuai standar regulasi.
                    </p>
                    <div class="hero-buttons">
                        <a href="{{ route('register') }}" class="btn btn-primary btn-lg me-3">
                            <i class="bi bi-rocket-takeoff me-2"></i>
                            Mulai Sekarang
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-play-circle me-2"></i>
                            Pelajari Lebih Lanjut
                        </a>
                    </div>
                    <div class="hero-stats">
                        <div class="stat-item">
                            <h3>150+</h3>
                            <p>Koperasi Terdaftar</p>
                        </div>
                        <div class="stat-item">
                            <h3>5000+</h3>
                            <p>Laporan Diproses</p>
                        </div>
                        <div class="stat-item">
                            <h3>99.9%</h3>
                            <p>Uptime System</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <div class="hero-image">
                    <div class="floating-card card-1">
                        <i class="bi bi-graph-up-arrow"></i>
                        <h5>Laporan Real-time</h5>
                        <p>Monitor kinerja koperasi secara langsung</p>
                    </div>
                    <div class="floating-card card-2">
                        <i class="bi bi-shield-check"></i>
                        <h5>Keamanan Terjamin</h5>
                        <p>Data koperasi aman dengan enkripsi tingkat bank</p>
                    </div>
                    <div class="floating-card card-3">
                        <i class="bi bi-people"></i>
                        <h5>Multi-User</h5>
                        <p>Kolaborasi tim yang efektif dan terorganisir</p>
                    </div>
                    <div class="hero-mockup">
                        <img src="{{ asset('images/dashboard-mockup.png') }}" alt="SIMKOP Dashboard" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="features-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center mb-5" data-aos="fade-up">
                <h2 class="section-title">Fitur Unggulan SIMKOP</h2>
                <p class="section-subtitle">
                    Solusi lengkap untuk manajemen koperasi modern dengan teknologi terdepan
                </p>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-file-earmark-bar-graph"></i>
                    </div>
                    <h4>Laporan Keuangan</h4>
                    <p>Generate laporan neraca, laba rugi, arus kas, dan perubahan ekuitas secara otomatis sesuai
                        standar akuntansi.</p>
                    <ul class="feature-list">
                        <li><i class="bi bi-check"></i> Neraca Koperasi</li>
                        <li><i class="bi bi-check"></i> Laporan Laba Rugi</li>
                        <li><i class="bi bi-check"></i> Arus Kas</li>
                        <li><i class="bi bi-check"></i> Perubahan Ekuitas</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h4>Manajemen Anggota</h4>
                    <p>Kelola data anggota, simpanan, dan piutang dengan sistem yang terintegrasi dan mudah digunakan.
                    </p>
                    <ul class="feature-list">
                        <li><i class="bi bi-check"></i> Database Anggota</li>
                        <li><i class="bi bi-check"></i> Simpanan Anggota</li>
                        <li><i class="bi bi-check"></i> Piutang Anggota</li>
                        <li><i class="bi bi-check"></i> NPL Tracking</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <h4>Keamanan Data</h4>
                    <p>Sistem keamanan berlapis dengan enkripsi data, audit trail, dan backup otomatis untuk melindungi
                        informasi koperasi.</p>
                    <ul class="feature-list">
                        <li><i class="bi bi-check"></i> Enkripsi End-to-End</li>
                        <li><i class="bi bi-check"></i> Audit Trail</li>
                        <li><i class="bi bi-check"></i> Backup Otomatis</li>
                        <li><i class="bi bi-check"></i> Role-based Access</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="400">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <h4>Dashboard Analytics</h4>
                    <p>Visualisasi data yang interaktif untuk membantu pengambilan keputusan strategis koperasi.</p>
                    <ul class="feature-list">
                        <li><i class="bi bi-check"></i> Real-time Charts</li>
                        <li><i class="bi bi-check"></i> KPI Monitoring</li>
                        <li><i class="bi bi-check"></i> Trend Analysis</li>
                        <li><i class="bi bi-check"></i> Custom Reports</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="500">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-cloud-check"></i>
                    </div>
                    <h4>Cloud-Based</h4>
                    <p>Akses sistem dari mana saja dengan koneksi internet, tanpa perlu instalasi software khusus.</p>
                    <ul class="feature-list">
                        <li><i class="bi bi-check"></i> Akses 24/7</li>
                        <li><i class="bi bi-check"></i> Multi-Device</li>
                        <li><i class="bi bi-check"></i> Auto-Sync</li>
                        <li><i class="bi bi-check"></i> Scalable</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="600">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="bi bi-headset"></i>
                    </div>
                    <h4>Support 24/7</h4>
                    <p>Tim support yang siap membantu koperasi dalam menggunakan sistem dengan optimal.</p>
                    <ul class="feature-list">
                        <li><i class="bi bi-check"></i> Live Chat</li>
                        <li><i class="bi bi-check"></i> Video Tutorial</li>
                        <li><i class="bi bi-check"></i> Training Online</li>
                        <li><i class="bi bi-check"></i> Technical Support</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Section -->
<section class="stats-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center mb-5" data-aos="fade-up">
                <h2 class="section-title text-white">SIMKOP dalam Angka</h2>
                <p class="section-subtitle text-white-50">
                    Kepercayaan koperasi di Muara Enim adalah prioritas utama kami
                </p>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-building"></i>
                    </div>
                    <h3 class="stat-number" data-count="150">0</h3>
                    <p class="stat-label">Koperasi Aktif</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <h3 class="stat-number" data-count="5000">0</h3>
                    <p class="stat-label">Laporan Diproses</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <h3 class="stat-number" data-count="25000">0</h3>
                    <p class="stat-label">Anggota Koperasi</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-award"></i>
                    </div>
                    <h3 class="stat-number" data-count="99">0</h3>
                    <p class="stat-label">% Kepuasan</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto text-center" data-aos="fade-up">
                <h2 class="cta-title">Siap Bergabung dengan SIMKOP?</h2>
                <p class="cta-subtitle">
                    Daftarkan koperasi Anda sekarang dan rasakan kemudahan mengelola laporan keuangan
                    dengan sistem yang telah dipercaya oleh ratusan koperasi di Muara Enim.
                </p>
                <div class="cta-buttons">
                    <a href="{{ route('register') }}" class="btn btn-primary btn-lg me-3">
                        <i class="bi bi-rocket-takeoff me-2"></i>
                        Daftar Gratis Sekarang
                    </a>
                    <a href="{{ route('login') }}" class="btn btn-outline-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Sudah Punya Akun?
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
