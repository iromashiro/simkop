// HERMES Advanced JavaScript - Performance & UX Enhancements

// Service Worker for Offline Support
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
            .then(function(registration) {
                console.log('ServiceWorker registration successful');
            })
            .catch(function(err) {
                console.log('ServiceWorker registration failed: ', err);
            });
    });
}

// Advanced HERMES Object with Performance Optimizations
window.HERMES = {
    ...window.HERMES,

    // Performance Monitoring
    performance: {
        marks: new Map(),

        mark(name) {
            this.marks.set(name, performance.now());
        },

        measure(name, startMark) {
            const start = this.marks.get(startMark);
            const duration = performance.now() - start;
            console.log(`${name}: ${duration.toFixed(2)}ms`);
            return duration;
        }
    },

    // Real-time Notifications
    notifications: {
        connection: null,

        init() {
            if (window.Echo) {
                this.connection = window.Echo.private(`cooperative.${window.HERMES.config.cooperativeId}`)
                    .listen('MemberRegistered', (e) => {
                        this.showNotification('Anggota baru bergabung: ' + e.member.name, 'info');
                    })
                    .listen('LoanApproved', (e) => {
                        this.showNotification('Pinjaman disetujui: ' + e.loan.account_number, 'success');
                    })
                    .listen('TransactionProcessed', (e) => {
                        this.showNotification('Transaksi berhasil diproses', 'success');
                        this.updateBalanceDisplay(e.account_id, e.new_balance);
                    });
            }
        },

        showNotification(message, type = 'info') {
            if (window.Alpine) {
                Alpine.store('toast').add(message, type);
            }

            // Browser notification if permission granted
            if (Notification.permission === 'granted') {
                new Notification('HERMES Koperasi', {
                    body: message,
                    icon: '/favicon.ico'
                });
            }
        },

        updateBalanceDisplay(accountId, newBalance) {
            const balanceElements = document.querySelectorAll(`[data-account-balance="${accountId}"]`);
            balanceElements.forEach(element => {
                element.textContent = this.formatCurrency(newBalance);
                element.classList.add('balance-updated');
                setTimeout(() => element.classList.remove('balance-updated'), 2000);
            });
        }
    },

    // Advanced Caching
    cache: {
        storage: new Map(),
        ttl: new Map(),

        set(key, value, ttlMinutes = 5) {
            this.storage.set(key, value);
            this.ttl.set(key, Date.now() + (ttlMinutes * 60 * 1000));
        },

        get(key) {
            if (this.ttl.get(key) < Date.now()) {
                this.storage.delete(key);
                this.ttl.delete(key);
                return null;
            }
            return this.storage.get(key);
        },

        clear() {
            this.storage.clear();
            this.ttl.clear();
        }
    },

    // Lazy Loading
    lazyLoad: {
        observer: null,

        init() {
            if ('IntersectionObserver' in window) {
                this.observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            this.loadElement(entry.target);
                            this.observer.unobserve(entry.target);
                        }
                    });
                });

                document.querySelectorAll('[data-lazy-load]').forEach(el => {
                    this.observer.observe(el);
                });
            }
        },

        loadElement(element) {
            const src = element.dataset.src;
            const type = element.dataset.lazyLoad;

            switch (type) {
                case 'image':
                    element.src = src;
                    break;
                case 'chart':
                    this.loadChart(element);
                    break;
                case 'table':
                    this.loadTable(element);
                    break;
            }
        },

        loadChart(element) {
            const chartType = element.dataset.chartType;
            const dataUrl = element.dataset.src;

            fetch(dataUrl)
                .then(response => response.json())
                .then(data => {
                    this.renderChart(element, chartType, data);
                });
        },

        loadTable(element) {
            const dataUrl = element.dataset.src;

            fetch(dataUrl)
                .then(response => response.json())
                .then(data => {
                    this.renderTable(element, data);
                });
        }
    },

    // Keyboard Shortcuts
    shortcuts: {
        init() {
            document.addEventListener('keydown', (e) => {
                // Ctrl/Cmd + K for global search
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    this.openGlobalSearch();
                }

                // Ctrl/Cmd + N for new entry (context-aware)
                if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                    e.preventDefault();
                    this.createNew();
                }

                // Escape to close modals
                if (e.key === 'Escape') {
                    this.closeModals();
                }

                // F1 for help
                if (e.key === 'F1') {
                    e.preventDefault();
                    this.showHelp();
                }
            });
        },

        openGlobalSearch() {
            const searchModal = document.getElementById('globalSearchModal');
            if (searchModal) {
                const modal = new bootstrap.Modal(searchModal);
                modal.show();
                setTimeout(() => {
                    document.getElementById('globalSearchInput')?.focus();
                }, 300);
            }
        },

        createNew() {
            const currentPath = window.location.pathname;
            const createRoutes = {
                '/members': '/members/create',
                '/savings': '/savings/create',
                '/loans': '/loans/create',
                '/journal-entries': '/journal-entries/create',
                '/accounts': '/accounts/create'
            };

            for (const [path, createPath] of Object.entries(createRoutes)) {
                if (currentPath.startsWith(path)) {
                    window.location.href = createPath;
                    break;
                }
            }
        },

        closeModals() {
            document.querySelectorAll('.modal.show').forEach(modal => {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) {
                    bsModal.hide();
                }
            });
        },

        showHelp() {
            const helpModal = document.getElementById('helpModal');
            if (helpModal) {
                const modal = new bootstrap.Modal(helpModal);
                modal.show();
            }
        }
    },

    // Form Auto-save
    autoSave: {
        forms: new Map(),
        interval: 30000, // 30 seconds

        init() {
            document.querySelectorAll('form[data-auto-save]').forEach(form => {
                this.enableAutoSave(form);
            });
        },

        enableAutoSave(form) {
            const formId = form.id || 'form_' + Date.now();
            form.id = formId;

            // Load saved data
            this.loadSavedData(form);

            // Set up auto-save
            const intervalId = setInterval(() => {
                this.saveFormData(form);
            }, this.interval);

            this.forms.set(formId, intervalId);

            // Clear on successful submit
            form.addEventListener('submit', () => {
                this.clearSavedData(form);
                clearInterval(intervalId);
                this.forms.delete(formId);
            });
        },

        saveFormData(form) {
            const formData = new FormData(form);
            const data = {};

            for (const [key, value] of formData.entries()) {
                data[key] = value;
            }

            localStorage.setItem(`autosave_${form.id}`, JSON.stringify({
                data: data,
                timestamp: Date.now()
            }));

            this.showAutoSaveIndicator();
        },

        loadSavedData(form) {
            const saved = localStorage.getItem(`autosave_${form.id}`);
            if (saved) {
                const { data, timestamp } = JSON.parse(saved);

                // Only load if less than 1 hour old
                if (Date.now() - timestamp < 3600000) {
                    Object.entries(data).forEach(([key, value]) => {
                        const input = form.querySelector(`[name="${key}"]`);
                        if (input && !input.value) {
                            input.value = value;
                        }
                    });

                    this.showAutoSaveRestored();
                }
            }
        },

        clearSavedData(form) {
            localStorage.removeItem(`autosave_${form.id}`);
        },

        showAutoSaveIndicator() {
            const indicator = document.getElementById('autoSaveIndicator');
            if (indicator) {
                indicator.style.display = 'block';
                setTimeout(() => {
                    indicator.style.display = 'none';
                }, 2000);
            }
        },

        showAutoSaveRestored() {
            HERMES.utils.showToast('Data formulir yang tersimpan telah dipulihkan', 'info');
        }
    },

    // Advanced Search
    search: {
        index: null,

        async init() {
            // Initialize search index
            if (window.lunr) {
                this.index = lunr(function() {
                    this.field('title');
                    this.field('content');
                    this.field('type');
                    this.ref('id');
                });

                // Load search data
                await this.loadSearchData();
            }
        },

        async loadSearchData() {
            try {
                const response = await fetch('/api/search/index');
                const data = await response.json();

                data.forEach(item => {
                    this.index.add(item);
                });
            } catch (error) {
                console.error('Failed to load search index:', error);
            }
        },

        search(query) {
            if (!this.index) return [];

            return this.index.search(query).map(result => ({
                ...result,
                score: result.score
            }));
        }
    }
};

// Initialize advanced features
document.addEventListener('DOMContentLoaded', function() {
    HERMES.performance.mark('app_init_start');

    // Initialize all advanced features
    HERMES.notifications.init();
    HERMES.lazyLoad.init();
    HERMES.shortcuts.init();
    HERMES.autoSave.init();
    HERMES.search.init();

    // Request notification permission
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }

    HERMES.performance.measure('App Initialization', 'app_init_start');
});

// Alpine.js Advanced Components
document.addEventListener('alpine:init', () => {
    // Advanced Data Table with Virtual Scrolling
    Alpine.data('advancedDataTable', (config = {}) => ({
        data: [],
        filteredData: [],
        displayData: [],
        loading: false,
        search: '',
        sortBy: config.sortBy || 'id',
        sortDirection: 'asc',
        currentPage: 1,
        perPage: config.perPage || 25,
        virtualScrolling: config.virtualScrolling || false,
        scrollTop: 0,
        itemHeight: 50,
        containerHeight: 400,

        get visibleItems() {
            if (!this.virtualScrolling) return this.paginatedData;

            const startIndex = Math.floor(this.scrollTop / this.itemHeight);
            const endIndex = Math.min(
                startIndex + Math.ceil(this.containerHeight / this.itemHeight) + 1,
                this.filteredData.length
            );

            return this.filteredData.slice(startIndex, endIndex).map((item, index) => ({
                ...item,
                _virtualIndex: startIndex + index
            }));
        },

        get totalHeight() {
            return this.filteredData.length * this.itemHeight;
        },

        get offsetY() {
            return Math.floor(this.scrollTop / this.itemHeight) * this.itemHeight;
        },

        handleScroll(event) {
            this.scrollTop = event.target.scrollTop;
        },

        async loadData(url) {
            this.loading = true;
            try {
                const cached = HERMES.cache.get(url);
                if (cached) {
                    this.data = cached;
                    this.filterData();
                    return;
                }

                const response = await HERMES.api.get(url);
                this.data = response.data || response;
                HERMES.cache.set(url, this.data);
                this.filterData();
            } catch (error) {
                console.error('Error loading data:', error);
                HERMES.utils.showToast('Error loading data', 'error');
            } finally {
                this.loading = false;
            }
        },

        filterData() {
            let filtered = this.data;

            if (this.search) {
                const searchLower = this.search.toLowerCase();
                filtered = filtered.filter(item =>
                    Object.values(item).some(value =>
                        String(value).toLowerCase().includes(searchLower)
                    )
                );
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

            this.filteredData = filtered;
        },

        exportData(format = 'csv') {
            const data = this.filteredData;

            if (format === 'csv') {
                this.exportCSV(data);
            } else if (format === 'excel') {
                this.exportExcel(data);
            }
        },

        exportCSV(data) {
            if (data.length === 0) return;

            const headers = Object.keys(data[0]);
            const csvContent = [
                headers.join(','),
                ...data.map(row =>
                    headers.map(header => `"${row[header] || ''}"`).join(',')
                )
            ].join('\n');

            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `export_${Date.now()}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }
    }));

    // Advanced Form with Validation
    Alpine.data('advancedForm', (config = {}) => ({
        data: config.data || {},
        errors: {},
        loading: false,
        isDirty: false,
        validationRules: config.rules || {},

        init() {
            // Watch for changes
            this.$watch('data', () => {
                this.isDirty = true;
                this.validateField();
            }, { deep: true });

            // Prevent accidental navigation
            window.addEventListener('beforeunload', (e) => {
                if (this.isDirty) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
        },

        validateField(field = null) {
            if (field) {
                this.validateSingleField(field);
            } else {
                // Debounced validation
                clearTimeout(this.validationTimeout);
                this.validationTimeout = setTimeout(() => {
                    this.validateAllFields();
                }, 300);
            }
        },

        validateSingleField(field) {
            const rules = this.validationRules[field];
            if (!rules) return;

            const value = this.data[field];
            const errors = [];

            rules.forEach(rule => {
                if (rule.required && (!value || value.trim() === '')) {
                    errors.push(rule.message || `${field} is required`);
                }

                if (rule.min && value && value.length < rule.min) {
                    errors.push(rule.message || `${field} must be at least ${rule.min} characters`);
                }

                if (rule.pattern && value && !rule.pattern.test(value)) {
                    errors.push(rule.message || `${field} format is invalid`);
                }
            });

            if (errors.length > 0) {
                this.errors[field] = errors;
            } else {
                delete this.errors[field];
            }
        },

        validateAllFields() {
            Object.keys(this.validationRules).forEach(field => {
                this.validateSingleField(field);
            });
        },

        async submit(url, method = 'POST') {
            this.validateAllFields();

            if (Object.keys(this.errors).length > 0) {
                HERMES.utils.showToast('Please fix validation errors', 'error');
                return;
            }

            this.loading = true;

            try {
                const response = await HERMES.api.request(url, {
                    method: method,
                    body: JSON.stringify(this.data)
                });

                this.isDirty = false;
                HERMES.utils.showToast(response.message || 'Data saved successfully', 'success');

                if (config.onSuccess) {
                    config.onSuccess(response);
                }

                return response;
            } catch (error) {
                if (error.status === 422) {
                    this.errors = error.errors || {};
                } else {
                    HERMES.utils.showToast('An error occurred', 'error');
                }
                throw error;
            } finally {
                this.loading = false;
            }
        }
    }));

    // Real-time Dashboard
    Alpine.data('realtimeDashboard', () => ({
        stats: {},
        charts: {},
        lastUpdate: null,
        updateInterval: 30000, // 30 seconds

        init() {
            this.loadStats();
            this.startRealTimeUpdates();
        },

        async loadStats() {
            try {
                const response = await HERMES.api.get('/api/dashboard/stats');
                this.stats = response;
                this.lastUpdate = new Date();
                this.updateCharts();
            } catch (error) {
                console.error('Failed to load dashboard stats:', error);
            }
        },

        startRealTimeUpdates() {
            setInterval(() => {
                this.loadStats();
            }, this.updateInterval);
        },

        updateCharts() {
            // Update existing charts with new data
            Object.keys(this.charts).forEach(chartId => {
                const chart = this.charts[chartId];
                if (chart && this.stats[chartId]) {
                    chart.data = this.stats[chartId];
                    chart.update('none'); // No animation for real-time updates
                }
            });
        }
    }));
});

// Performance Monitoring
window.addEventListener('load', function() {
    // Measure page load time
    const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
    console.log(`Page load time: ${loadTime}ms`);

    // Monitor long tasks
    if ('PerformanceObserver' in window) {
        const observer = new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                if (entry.duration > 50) {
                    console.warn(`Long task detected: ${entry.duration}ms`);
                }
            }
        });
        observer.observe({ entryTypes: ['longtask'] });
    }
});

// Error Tracking
window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.error);

    // Send error to logging service
    if (window.HERMES.config.errorReporting) {
        fetch('/api/errors', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': HERMES.config.csrfToken
            },
            body: JSON.stringify({
                message: e.message,
                filename: e.filename,
                lineno: e.lineno,
                colno: e.colno,
                stack: e.error?.stack,
                url: window.location.href,
                userAgent: navigator.userAgent
            })
        });
    }
});

// Export for global use
window.HERMES = HERMES;
