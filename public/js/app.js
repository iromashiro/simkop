// HERMES Koperasi - Custom JavaScript

// Global App Object
window.HERMES = {
    // Configuration
    config: {
        apiUrl: '/api',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
        locale: 'id'
    },

    // Utilities
    utils: {
        // Format currency
        formatCurrency(amount) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(amount);
        },

        // Format number
        formatNumber(number) {
            return new Intl.NumberFormat('id-ID').format(number);
        },

        // Format date
        formatDate(date, options = {}) {
            const defaultOptions = {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            return new Intl.DateTimeFormat('id-ID', { ...defaultOptions, ...options }).format(new Date(date));
        },

        // Show loading
        showLoading() {
            if (window.Alpine) {
                Alpine.store('loading').show();
            }
        },

        // Hide loading
        hideLoading() {
            if (window.Alpine) {
                Alpine.store('loading').hide();
            }
        },

        // Show toast
        showToast(message, type = 'info', duration = 5000) {
            if (window.Alpine) {
                Alpine.store('toast').add(message, type, duration);
            }
        },

        // Confirm dialog
        confirm(message, callback) {
            if (window.confirm(message)) {
                callback();
            }
        },

        // Debounce function
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    },

    // API Helper
    api: {
        async request(url, options = {}) {
            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': HERMES.config.csrfToken,
                    'Accept': 'application/json'
                }
            };

            const response = await fetch(url, { ...defaultOptions, ...options });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return response.json();
        },

        async get(url) {
            return this.request(url, { method: 'GET' });
        },

        async post(url, data) {
            return this.request(url, {
                method: 'POST',
                body: JSON.stringify(data)
            });
        },

        async put(url, data) {
            return this.request(url, {
                method: 'PUT',
                body: JSON.stringify(data)
            });
        },

        async delete(url) {
            return this.request(url, { method: 'DELETE' });
        }
    }
};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-dismiss alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Form validation enhancement
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Auto-resize textareas
    const textareas = document.querySelectorAll('textarea[data-auto-resize]');
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });

    // Number input formatting
    const numberInputs = document.querySelectorAll('input[data-format="number"]');
    numberInputs.forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value.replace(/[^\d]/g, '');
            this.value = HERMES.utils.formatNumber(value);
        });
    });

    // Currency input formatting
    const currencyInputs = document.querySelectorAll('input[data-format="currency"]');
    currencyInputs.forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value.replace(/[^\d]/g, '');
            this.value = value ? HERMES.utils.formatNumber(value) : '';
        });
    });
});

// Alpine.js components
document.addEventListener('alpine:init', () => {
    // Data table component
    Alpine.data('dataTable', (config = {}) => ({
        data: [],
        loading: false,
        search: '',
        sortBy: config.sortBy || 'id',
        sortDirection: 'asc',
        currentPage: 1,
        perPage: config.perPage || 10,

        get filteredData() {
            let filtered = this.data;

            // Search filter
            if (this.search) {
                filtered = filtered.filter(item => {
                    return Object.values(item).some(value =>
                        String(value).toLowerCase().includes(this.search.toLowerCase())
                    );
                });
            }

            // Sort
            filtered.sort((a, b) => {
                let aVal = a[this.sortBy];
                let bVal = b[this.sortBy];

                if (this.sortDirection === 'desc') {
                    [aVal, bVal] = [bVal, aVal];
                }

                return aVal > bVal ? 1 : -1;
            });

            return filtered;
        },

        get paginatedData() {
            const start = (this.currentPage - 1) * this.perPage;
            const end = start + this.perPage;
            return this.filteredData.slice(start, end);
        },

        get totalPages() {
            return Math.ceil(this.filteredData.length / this.perPage);
        },

        sort(column) {
            if (this.sortBy === column) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortBy = column;
                this.sortDirection = 'asc';
            }
        },

        async loadData(url) {
            this.loading = true;
            try {
                const response = await HERMES.api.get(url);
                this.data = response.data || response;
            } catch (error) {
                console.error('Error loading data:', error);
                HERMES.utils.showToast('Error loading data', 'error');
            } finally {
                this.loading = false;
            }
        }
    }));

    // Form component
    Alpine.data('form', (config = {}) => ({
        data: config.data || {},
        errors: {},
        loading: false,

        async submit(url, method = 'POST') {
            this.loading = true;
            this.errors = {};

            try {
                const response = await HERMES.api.request(url, {
                    method: method,
                    body: JSON.stringify(this.data)
                });

                HERMES.utils.showToast(response.message || 'Data berhasil disimpan', 'success');

                if (config.onSuccess) {
                    config.onSuccess(response);
                }

                return response;
            } catch (error) {
                if (error.status === 422) {
                    this.errors = error.errors || {};
                } else {
                    HERMES.utils.showToast('Terjadi kesalahan', 'error');
                }
                throw error;
            } finally {
                this.loading = false;
            }
        },

        hasError(field) {
            return this.errors[field] && this.errors[field].length > 0;
        },

        getError(field) {
            return this.errors[field] ? this.errors[field][0] : '';
        }
    }));
});

// Export for global use
window.HERMES = HERMES;
