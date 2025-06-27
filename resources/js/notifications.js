/**
 * Notification System
 * Simple polling-based notification system
 */
class NotificationSystem {
    constructor() {
        this.pollingInterval = 30000; // 30 seconds
        this.isPolling = false;
        this.unreadCount = 0;

        this.init();
    }

    init() {
        this.setupEventListeners();
        this.startPolling();
        this.loadInitialNotifications();
    }

    setupEventListeners() {
        // Mark as read when notification is clicked
        document.addEventListener('click', (e) => {
            if (e.target.closest('.notification-item[data-id]')) {
                const notificationId = e.target.closest('.notification-item').dataset.id;
                this.markAsRead(notificationId);
            }
        });

        // Mark all as read button
        const markAllBtn = document.getElementById('mark-all-read-btn');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', () => this.markAllAsRead());
        }

        // Refresh notifications button
        const refreshBtn = document.getElementById('refresh-notifications-btn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.loadNotifications());
        }
    }

    async loadInitialNotifications() {
        await this.loadNotifications();
        await this.updateUnreadCount();
    }

    async loadNotifications(limit = 10) {
        try {
            const response = await fetch(`/api/notifications?limit=${limit}`, {
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'Accept': 'application/json',
                }
            });

            if (!response.ok) {
                throw new Error('Failed to load notifications');
            }

            const data = await response.json();
            this.renderNotifications(data.data);
            this.unreadCount = data.meta.unread_count;
            this.updateUnreadBadge();

        } catch (error) {
            console.error('Error loading notifications:', error);
            this.showError('Gagal memuat notifikasi');
        }
    }

    async updateUnreadCount() {
        try {
            const response = await fetch('/api/notifications/count', {
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'Accept': 'application/json',
                }
            });

            if (!response.ok) {
                throw new Error('Failed to get notification count');
            }

            const data = await response.json();
            this.unreadCount = data.count;
            this.updateUnreadBadge();

        } catch (error) {
            console.error('Error getting notification count:', error);
        }
    }

    async markAsRead(notificationId) {
        try {
            const response = await fetch(`/api/notifications/${notificationId}/read`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                }
            });

            if (!response.ok) {
                throw new Error('Failed to mark notification as read');
            }

            // Update UI
            const notificationElement = document.querySelector(`[data-id="${notificationId}"]`);
            if (notificationElement) {
                notificationElement.classList.remove('unread');
                notificationElement.classList.add('read');
            }

            // Update unread count
            if (this.unreadCount > 0) {
                this.unreadCount--;
                this.updateUnreadBadge();
            }

        } catch (error) {
            console.error('Error marking notification as read:', error);
            this.showError('Gagal menandai notifikasi sebagai dibaca');
        }
    }

    async markAllAsRead() {
        try {
            const response = await fetch('/api/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                }
            });

            if (!response.ok) {
                throw new Error('Failed to mark all notifications as read');
            }

            // Update UI
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                item.classList.add('read');
            });

            this.unreadCount = 0;
            this.updateUnreadBadge();
            this.showSuccess('Semua notifikasi telah ditandai sebagai dibaca');

        } catch (error) {
            console.error('Error marking all notifications as read:', error);
            this.showError('Gagal menandai semua notifikasi sebagai dibaca');
        }
    }

    renderNotifications(notifications) {
        const container = document.getElementById('notifications-container');
        if (!container) return;

        if (notifications.length === 0) {
            container.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-bell-slash fa-2x mb-2"></i>
                    <p>Tidak ada notifikasi</p>
                </div>
            `;
            return;
        }

        container.innerHTML = notifications.map(notification => `
            <div class="notification-item ${notification.is_read ? 'read' : 'unread'}"
                 data-id="${notification.id}">
                <div class="d-flex">
                    <div class="notification-icon me-3">
                        <i class="${notification.icon_class}"></i>
                    </div>
                    <div class="notification-content flex-grow-1">
                        <h6 class="notification-title mb-1">${notification.title}</h6>
                        <p class="notification-message mb-1">${notification.message}</p>
                        <small class="notification-time text-muted">${notification.created_at}</small>
                        ${notification.cooperative ? `
                            <small class="notification-cooperative d-block text-info">
                                ${notification.cooperative.name}
                            </small>
                        ` : ''}
                    </div>
                    ${!notification.is_read ? `
                        <div class="notification-badge">
                            <span class="badge bg-primary rounded-pill"></span>
                        </div>
                    ` : ''}
                </div>
            </div>
        `).join('');
    }

    updateUnreadBadge() {
        const badges = document.querySelectorAll('.notification-badge-count');
        badges.forEach(badge => {
            if (this.unreadCount > 0) {
                badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        });
    }

    startPolling() {
        if (this.isPolling) return;

        this.isPolling = true;
        this.pollingTimer = setInterval(() => {
            this.updateUnreadCount();
        }, this.pollingInterval);
    }

    stopPolling() {
        if (this.pollingTimer) {
            clearInterval(this.pollingTimer);
            this.pollingTimer = null;
        }
        this.isPolling = false;
    }

    getAuthToken() {
        // Get token from meta tag or localStorage
        const tokenMeta = document.querySelector('meta[name="api-token"]');
        if (tokenMeta) {
            return tokenMeta.content;
        }

        return localStorage.getItem('auth_token') || '';
    }

    showSuccess(message) {
        this.showToast(message, 'success');
    }

    showError(message) {
        this.showToast(message, 'error');
    }

    showToast(message, type = 'info') {
        // Simple toast implementation
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.textContent = message;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('show');
        }, 100);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    }
}

// Initialize notification system when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notificationSystem = new NotificationSystem();
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.notificationSystem) {
        window.notificationSystem.stopPolling();
    }
});
