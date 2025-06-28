{{-- resources/views/auth/reset-password.blade.php --}}
@extends('layouts.guest')

@section('title', 'Reset Password')

@section('content')
<div class="text-center mb-4">
    <div class="mb-3">
        <i class="bi bi-shield-lock text-primary" style="font-size: 3rem;"></i>
    </div>
    <h4 class="fw-bold text-dark mb-2">Reset Password</h4>
    <p class="text-muted">Masukkan password baru untuk akun Anda</p>
</div>

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

<form method="POST" action="{{ route('password.store') }}" x-data="resetPasswordForm()">
    @csrf

    <!-- Password Reset Token -->
    <input type="hidden" name="token" value="{{ $request->route('token') }}">

    <!-- Email Address -->
    <div class="mb-3">
        <label for="email" class="form-label">
            <i class="bi bi-envelope me-1"></i>
            Email
        </label>
        <input id="email" class="form-control @error('email') is-invalid @enderror" type="email" name="email"
            value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" readonly>
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
            Password Baru
        </label>
        <div class="position-relative">
            <input id="password" class="form-control @error('password') is-invalid @enderror" type="password"
                name="password" required autocomplete="new-password" placeholder="Masukkan password baru"
                x-ref="passwordInput" @input="checkPasswordStrength($event.target.value)">
            <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y pe-3"
                style="border: none; background: none; z-index: 10;" @click="togglePassword('password')"
                x-text="showPassword ? 'Sembunyikan' : 'Tampilkan'">
            </button>
        </div>

        <!-- Password Strength Indicator -->
        <div class="mt-2" x-show="passwordStrength.show">
            <div class="progress" style="height: 4px;">
                <div class="progress-bar" :class="passwordStrength.class" :style="`width: ${passwordStrength.width}%`">
                </div>
            </div>
            <small class="text-muted" x-text="passwordStrength.text"></small>
        </div>

        @error('password')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>

    <!-- Confirm Password -->
    <div class="mb-4">
        <label for="password_confirmation" class="form-label">
            <i class="bi bi-lock-fill me-1"></i>
            Konfirmasi Password Baru
        </label>
        <div class="position-relative">
            <input id="password_confirmation" class="form-control" type="password" name="password_confirmation" required
                autocomplete="new-password" placeholder="Konfirmasi password baru" x-ref="confirmPasswordInput">
            <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y pe-3"
                style="border: none; background: none; z-index: 10;" @click="togglePassword('confirm')"
                x-text="showConfirmPassword ? 'Sembunyikan' : 'Tampilkan'">
            </button>
        </div>
    </div>

    <!-- Submit Button -->
    <div class="d-grid mb-4">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-check-circle me-2"></i>
            Reset Password
        </button>
    </div>

    <!-- Links -->
    <div class="text-center">
        <a href="{{ route('login') }}" class="text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>
            Kembali ke Login
        </a>
    </div>
</form>

@push('scripts')
<script>
    function resetPasswordForm() {
    return {
        showPassword: false,
        showConfirmPassword: false,
        passwordStrength: {
            show: false,
            width: 0,
            class: '',
            text: ''
        },

        togglePassword(type) {
            if (type === 'password') {
                this.showPassword = !this.showPassword;
                const input = this.$refs.passwordInput;
                input.type = this.showPassword ? 'text' : 'password';
            } else {
                this.showConfirmPassword = !this.showConfirmPassword;
                const input = this.$refs.confirmPasswordInput;
                input.type = this.showConfirmPassword ? 'text' : 'password';
            }
        },

        checkPasswordStrength(password) {
            this.passwordStrength.show = password.length > 0;

            let score = 0;
            let feedback = [];

            if (password.length >= 8) score += 25;
            else feedback.push('minimal 8 karakter');

            if (/[A-Z]/.test(password)) score += 25;
            else feedback.push('huruf besar');

            if (/[a-z]/.test(password)) score += 25;
            else feedback.push('huruf kecil');

            if (/[\d\W]/.test(password)) score += 25;
            else feedback.push('angka/simbol');

            this.passwordStrength.width = score;

            if (score < 50) {
                this.passwordStrength.class = 'bg-danger';
                this.passwordStrength.text = 'Lemah - Tambahkan: ' + feedback.join(', ');
            } else if (score < 75) {
                this.passwordStrength.class = 'bg-warning';
                this.passwordStrength.text = 'Sedang - Tambahkan: ' + feedback.join(', ');
            } else if (score < 100) {
                this.passwordStrength.class = 'bg-info';
                this.passwordStrength.text = 'Baik - Tambahkan: ' + feedback.join(', ');
            } else {
                this.passwordStrength.class = 'bg-success';
                this.passwordStrength.text = 'Sangat Kuat';
            }
        }
    }
}
</script>
@endpush
@endsection
