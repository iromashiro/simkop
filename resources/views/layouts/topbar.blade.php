{{-- resources/views/layouts/topbar.blade.php --}}
<div class="d-flex justify-content-between align-items-center w-100">
    <!-- Left Side - Sidebar Toggle & Breadcrumb -->
    <div class="d-flex align-items-center">
        <button type="button" class="btn btn-link text-dark p-0 me-3" onclick="SIMKOP.toggleSidebar()">
            <i class="bi bi-list fs-4"></i>
        </button>

        <div class="d-none d-md-block">
            <span class="text-muted">{{ auth()->user()->cooperative->name ?? 'SIMKOP' }}</span>
        </div>
    </div>

    <!-- Right Side - User Menu & Notifications -->
    <div class="d-flex align-items-center gap-3">
        <!-- Notifications -->
        <div class="dropdown">
            <button class="btn btn-link text-dark p-0 position-relative" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-bell fs-5"></i>
                @if($unreadNotifications ?? 0 > 0)
                <span class="notification-badge">{{ $unreadNotifications }}</span>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-end" style="width: 300px;">
                <div class="dropdown-header d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Notifikasi</span>
                    @if($unreadNotifications ?? 0 > 0)
                    <small class="text-muted">{{ $unreadNotifications }} baru</small>
                    @endif
                </div>
                <div class="dropdown-divider"></div>

                @forelse($recentNotifications ?? [] as $notification)
                <a class="dropdown-item {{ $notification->read_at ? '' : 'bg-light' }}"
                    href="{{ $notification->action_url ?? '#' }}">
                    <div class="d-flex">
                        <div class="flex-shrink-0 me-2">
                            <i class="bi bi-{{ $notification->icon ?? 'info-circle' }} text-primary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold small">{{ $notification->title }}</div>
                            <div class="text-muted small">{{ Str::limit($notification->message, 50) }}</div>
                            <div class="text-muted small">{{ $notification->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                </a>
                @empty
                <div class="dropdown-item text-center text-muted">
                    <i class="bi bi-bell-slash"></i>
                    <div>Tidak ada notifikasi</div>
                </div>
                @endforelse

                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-center" href="{{ route('notifications.index') }}">
                    Lihat Semua Notifikasi
                </a>
            </div>
        </div>

        <!-- User Menu -->
        <div class="dropdown">
            <button class="btn btn-link text-dark text-decoration-none d-flex align-items-center" type="button"
                data-bs-toggle="dropdown">
                <div class="me-2 text-end d-none d-md-block">
                    <div class="fw-bold small">{{ auth()->user()->name }}</div>
                    <div class="text-muted small">{{ auth()->user()->getRoleNames()->first() }}</div>
                </div>
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                    style="width: 40px; height: 40px;">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
                <i class="bi bi-chevron-down ms-1"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <div class="dropdown-header">
                        <div class="fw-bold">{{ auth()->user()->name }}</div>
                        <div class="text-muted small">{{ auth()->user()->email }}</div>
                    </div>
                </li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('profile.edit') }}">
                        <i class="bi bi-person me-2"></i>
                        Profil Saya
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('notifications.index') }}">
                        <i class="bi bi-bell me-2"></i>
                        Notifikasi
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
