<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
    <div class="container-fluid">
        <!-- Sidebar Toggle -->
        <button class="btn btn-outline-light me-3" type="button" @click="sidebarOpen = !sidebarOpen"
            :aria-expanded="sidebarOpen">
            <i class="bi bi-list"></i>
        </button>

        <!-- Brand -->
        <a class="navbar-brand fw-bold" href="{{ route('dashboard') }}">
            <i class="bi bi-building-gear me-2"></i>
            HERMES
        </a>

        <!-- Cooperative Info -->
        @if(auth()->user()->primaryCooperative())
        <span class="navbar-text text-white-50 d-none d-md-inline">
            {{ auth()->user()->primaryCooperative()->name }}
        </span>
        @endif

        <!-- Right Side -->
        <div class="navbar-nav ms-auto d-flex flex-row align-items-center">
            <!-- Theme Toggle -->
            <button class="btn btn-outline-light btn-sm me-2" @click="darkMode = !darkMode"
                :title="darkMode ? 'Mode Terang' : 'Mode Gelap'">
                <i :class="darkMode ? 'bi bi-sun' : 'bi bi-moon'"></i>
            </button>

            <!-- Notifications -->
            <div class="dropdown me-2">
                <button class="btn btn-outline-light btn-sm position-relative" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                        x-show="notifications.length > 0" x-text="notifications.length"></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" style="width: 300px;">
                    <li>
                        <h6 class="dropdown-header">Notifikasi</h6>
                    </li>
                    <template x-for="notification in notifications.slice(0, 5)" :key="notification.id">
                        <li>
                            <a class="dropdown-item" href="#" x-text="notification.message"></a>
                        </li>
                    </template>
                    <li x-show="notifications.length === 0">
                        <span class="dropdown-item-text text-muted">Tidak ada notifikasi</span>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <a class="dropdown-item text-center" href="#">
                            Lihat Semua Notifikasi
                        </a>
                    </li>
                </ul>
            </div>

            <!-- User Menu -->
            <div class="dropdown">
                <button class="btn btn-outline-light btn-sm d-flex align-items-center" type="button"
                    data-bs-toggle="dropdown">
                    <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}"
                        class="rounded-circle me-2" width="24" height="24">
                    <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                    <i class="bi bi-chevron-down ms-1"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <h6 class="dropdown-header">
                            {{ auth()->user()->name }}
                            <br>
                            <small class="text-muted">{{ auth()->user()->email }}</small>
                        </h6>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('users.profile', auth()->user()) }}">
                            <i class="bi bi-person me-2"></i>
                            Profil Saya
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('settings.index') }}">
                            <i class="bi bi-gear me-2"></i>
                            Pengaturan
                        </a>
                    </li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>
                                Keluar
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
