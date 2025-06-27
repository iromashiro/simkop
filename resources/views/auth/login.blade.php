@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
<div class="card shadow-lg border-0">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-primary">Selamat Datang</h3>
            <p class="text-muted">Masuk ke akun HERMES Anda</p>
        </div>

        <form method="POST" action="{{ route('login') }}" x-data="loginForm()">
            @csrf

            <!-- Email -->
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-envelope"></i>
                    </span>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                        name="email" value="{{ old('email') }}" required autofocus x-model="form.email">
                </div>
                @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <!-- Password -->
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input :type="showPassword ? 'text' : 'password'"
                        class="form-control @error('password') is-invalid @enderror" id="password" name="password"
                        required x-model="form.password">
                    <button type="button" class="btn btn-outline-secondary" @click="showPassword = !showPassword">
                        <i :class="showPassword ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                    </button>
                </div>
                @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <!-- Remember Me -->
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember" x-model="form.remember">
                <label class="form-check-label" for="remember">
                    Ingat saya
                </label>
            </div>

            <!-- Submit Button -->
            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-primary btn-lg" :disabled="loading" @click="loading = true">
                    <span x-show="!loading">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Masuk
                    </span>
                    <span x-show="loading">
                        <span class="spinner-border spinner-border-sm me-2"></span>
                        Memproses...
                    </span>
                </button>
            </div>

            <!-- Forgot Password -->
            @if (Route::has('password.request'))
            <div class="text-center">
                <a href="{{ route('password.request') }}" class="text-decoration-none">
                    Lupa password?
                </a>
            </div>
            @endif
        </form>
    </div>
</div>

@push('scripts')
<script>
    function loginForm() {
    return {
        form: {
            email: '',
            password: '',
            remember: false
        },
        showPassword: false,
        loading: false
    }
}
</script>
@endpush
@endsection
