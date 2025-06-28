{{-- resources/views/auth/forgot-password.blade.php --}}
@extends('layouts.guest')

@section('title', 'Lupa Password')

@section('content')
<div class="text-center mb-4">
    <div class="mb-3">
        <i class="bi bi-key text-primary" style="font-size: 3rem;"></i>
    </div>
    <h4 class="fw-bold text-dark mb-2">Lupa Password?</h4>
    <p class="text-muted">Tidak masalah! Masukkan email Anda dan kami akan mengirimkan link reset password.</p>
</div>

<!-- Session Status -->
@if (session('status'))
<div class="alert alert-success mb-4" role="alert">
    <i class="bi bi-check-circle me-2"></i>
    {{ session('status') }}
</div>
@endif

<!-- Validation Errors -->
@if ($errors->any())
<div class="alert alert-danger mb-4" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Oops!</strong> Ada masalah dengan email Anda.
    <ul class="mb-0 mt-2">
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('password.email') }}">
    @csrf

    <!-- Email Address -->
    <div class="mb-4">
        <label for="email" class="form-label">
            <i class="bi bi-envelope me-1"></i>
            Email
        </label>
        <input id="email" class="form-control @error('email') is-invalid @enderror" type="email" name="email"
            value="{{ old('email') }}" required autofocus placeholder="Masukkan email Anda">
        @error('email')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>

    <!-- Submit Button -->
    <div class="d-grid mb-4">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-send me-2"></i>
            Kirim Link Reset Password
        </button>
    </div>

    <!-- Links -->
    <div class="text-center">
        <a href="{{ route('login') }}" class="text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>
            Kembali ke Login
        </a>

        <div class="mt-3">
            <span class="text-muted">Belum punya akun?</span>
            <a href="{{ route('register') }}" class="text-decoration-none fw-semibold">
                Daftar sekarang
            </a>
        </div>
    </div>
</form>
@endsection
