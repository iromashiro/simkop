{{-- resources/views/auth/register.blade.php --}}
@extends('layouts.guest')

@section('title', 'Daftar')

@section('content')
<div class="text-center mb-4">
    <h4 class="fw-bold text-dark mb-2">Bergabung dengan SIMKOP</h4>
    <p class="text-muted">Daftarkan koperasi Anda sekarang</p>
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

<form method="POST" action="{{ route('register') }}" x-data="registerForm()">
    @csrf

    <!-- Name -->
    <div class="mb-3">
        <label for="name" class="form-label">
            <i class="bi bi-person me-1"></i>
            Nama Lengkap
        </label>
        <input id="name" class="form-control @error('name') is-invalid @enderror" type="text" name="name"
            value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Masukkan nama lengkap Anda">
        @error('name')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>

    <!-- Email Address -->
    <div class="mb-3">
        <label for="email" class="form-label">
            <i class="bi bi-envelope me-1"></i>
            Email
        </label>
        <input id="email" class="form-control @error('email') is-invalid @enderror" type="email" name="email"
            value="{{ old('email') }}" required autocomplete="username" placeholder="Masukkan email Anda">
        @error('email')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>

    <!-- Cooperative Name -->
    <div class="mb-3">
        <label for="cooperative_name" class="form-label">
            <i class="bi bi-building me-1"></i>
            Nama Koperasi
        </label>
        <input id="cooperative_name" class="form-control @error('cooperative_name') is-invalid @enderror" type="text"
            name="cooperative_name" value="{{ old('cooperative_name') }}" required placeholder="Masukkan nama koperasi">
        @error('cooperative_name')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
        @enderror
    </div>

    <!-- Phone -->
    <div class="mb-3">
        <label for="phone" class="form-label">
            <i class="bi bi-telephone me-1"></i>
            Nomor Telepon
        </label>
        <input id="phone" class="form-control @error('phone') is-invalid @enderror" type="tel" name="phone"
            value="{{ old('phone') }}" required placeholder="Masukkan nomor telepon">
        @error('phone')
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
                name="password" required autocomplete="new-password" placeholder="Masukkan password"
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
    <div class="mb-3">
        <label for="password_confirmation" class="form-label">
            <i class="bi bi-lock-fill me-1"></i>
            Konfirmasi Password
        </label>
        <div class="position-relative">
            <input id="password_confirmation" class="form-control" type="password" name="password_confirmation" required
                autocomplete="new-password" placeholder="Konfirmasi password" x-ref="confirmPasswordInput">
            <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y pe-3"
                style="border: none; background: none; z-index: 10;" @click="togglePassword('confirm')"
                x-text="showConfirmPassword ? 'Sembunyikan' : 'Tampilkan'">
            </button>
        </div>
    </div>

    <!-- Terms Agreement -->
    <div class="mb-4">
        <div class="form-check">
            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" id="terms" name="terms"
                required>
            <label class="form-check-label" for="terms">
                Saya setuju dengan <a href="#" class="text-decoration-none">Syarat & Ketentuan</a>
                dan <a href="#" class="text-decoration-none">Kebijakan Privasi</a>
            </label>
            @error('terms')
            <div class="invalid-feedback">
                {{ $message }}
            </div>
            @enderror
        </div>
    </div>

    <!-- Submit Button -->
    <div class="d-grid mb-4">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-person-plus me-2"></i>
            Daftar Sekarang
        </button>
    </div>

    <!-- Links -->
    <div class="text-center">
        <span class="text-muted">Sudah punya akun?</span>
        <a href="{{ route('login') }}" class="text-decoration-none fw-semibold">
            Masuk sekarang
        </a>

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
    function registerForm() {
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

            // Length check
            if (password.length >= 8) score += 25;
            else feedback.push('minimal 8 karakter');

            // Uppercase check
            if (/[A-Z]/.test(password)) score += 25;
            else feedback.push('huruf besar');

            // Lowercase check
            if (/[a-z]/.test(password)) score += 25;
            else feedback.push('huruf kecil');

            // Number or special char check
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
