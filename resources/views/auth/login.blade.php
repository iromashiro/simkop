{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<div class="text-center mb-4">
    <h4 class="fw-bold text-dark mb-2">Selamat Datang Kembali!</h4>
    <p class="text-muted">Masuk ke akun SIMKOP Anda</p>
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
    <strong>Oops!</strong> Ada beberapa masalah dengan input Anda.
    <ul class="mb-0 mt-2">
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('login') }}" x-data="loginForm()">
    @csrf

    <!-- Email Address -->
    <div class="mb-3">
        <label for="email" class="form-label">
            <i class="bi bi-envelope me-1"></i>
            Email
        </label>
        <input id="email" class="form-control @error('email') is-invalid @enderror" type="email" name="email"
            value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="Masukkan email Anda">
        @error('email')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>

    <!-- Password -->
    <div class="mb-3">
        <label for="password" class="form-label">
            <i class="bi bi-lock me-1"></i>
            Password
        </label>
        <div class="position-relative">
            <input id="password" class="form-control @error('password') is-invalid @enderror" type="password"
                name="password" required autocomplete="current-password" placeholder="Masukkan password Anda"
                x-ref="passwordInput">
            <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y pe-3"
                style="border: none; background: none; z-index: 10;" @click="togglePassword()"
                x-text="showPassword ? 'Sembunyikan' : 'Tampilkan'">
            </button>
        </div>
        @error('password')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>

    <!-- Remember Me -->
    <div class="mb-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="remember_me" name="remember">
            <label class="form-check-label" for="remember_me">
                Ingat saya
            </label>
        </div>
    </div>

    <!-- Submit Button -->
    <div class="d-grid mb-4">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-box-arrow-in-right me-2"></i>
            Masuk
        </button>
    </div>

    <!-- Links -->
    <div class="text-center">
        @if (Route::has('password.request'))
        <a class="text-decoration-none" href="{{ route('password.request') }}">
            <i class="bi bi-question-circle me-1"></i>
            Lupa password?
        </a>
        @endif

        <div class="mt-3">
            <span class="text-muted">Belum punya akun?</span>
            <a href="{{ route('register') }}" class="text-decoration-none fw-semibold">
                Daftar sekarang
            </a>
        </div>

        <div class="mt-3">
            <a href="{{ route('guest.dashboard') }}" class="text-decoration-none">
                <i class="bi bi-house me-1"></i>
                Kembali ke Beranda
            </a>
        </div>
    </div>
</form>

@push('scripts')
<script>
    function loginForm() {
    return {
        showPassword: false,

        togglePassword() {
            this.showPassword = !this.showPassword;
            const input = this.$refs.passwordInput;
            input.type = this.showPassword ? 'text' : 'password';
        }
    }
}
</script>
@endpush
@endsection
