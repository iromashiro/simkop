<!-- Global Search Modal -->
<div class="modal fade" id="globalSearchModal" tabindex="-1" aria-labelledby="globalSearchLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" x-data="globalSearch()" x-init="init()">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="globalSearchLabel">
                    <i class="bi bi-search me-2"></i>
                    Pencarian Global
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Search Input -->
                <div class="mb-3">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control" id="globalSearchInput"
                            placeholder="Cari anggota, transaksi, akun, atau laporan..." x-model="query"
                            @input.debounce.300ms="search()" @keydown.enter="selectFirst()"
                            @keydown.arrow-down.prevent="navigateDown()" @keydown.arrow-up.prevent="navigateUp()">
                    </div>
                </div>

                <!-- Search Filters -->
                <div class="mb-3">
                    <div class="btn-group" role="group" aria-label="Search filters">
                        <input type="radio" class="btn-check" name="searchType" id="searchAll" value="all"
                            x-model="searchType" @change="search()">
                        <label class="btn btn-outline-primary btn-sm" for="searchAll">Semua</label>

                        <input type="radio" class="btn-check" name="searchType" id="searchMembers" value="members"
                            x-model="searchType" @change="search()">
                        <label class="btn btn-outline-primary btn-sm" for="searchMembers">Anggota</label>

                        <input type="radio" class="btn-check" name="searchType" id="searchTransactions"
                            value="transactions" x-model="searchType" @change="search()">
                        <label class="btn btn-outline-primary btn-sm" for="searchTransactions">Transaksi</label>

                        <input type="radio" class="btn-check" name="searchType" id="searchAccounts" value="accounts"
                            x-model="searchType" @change="search()">
                        <label class="btn btn-outline-primary btn-sm" for="searchAccounts">Akun</label>
                    </div>
                </div>

                <!-- Loading State -->
                <div x-show="loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Mencari...</span>
                    </div>
                    <p class="mt-2 text-muted">Mencari...</p>
                </div>

                <!-- Search Results -->
                <div x-show="!loading && results.length > 0" class="search-results">
                    <template x-for="(result, index) in results" :key="result.id">
                        <div class="search-result-item p-3 border rounded mb-2 cursor-pointer"
                            :class="{ 'bg-primary text-white': selectedIndex === index }" @click="selectResult(result)"
                            @mouseenter="selectedIndex = index">

                            <!-- Result Icon -->
                            <div class="d-flex align-items-start">
                                <div class="flex-shrink-0 me-3">
                                    <div class="result-icon rounded-circle d-flex align-items-center justify-content-center"
                                        :class="{
                                             'bg-success': result.type === 'member',
                                             'bg-info': result.type === 'transaction',
                                             'bg-warning': result.type === 'account',
                                             'bg-primary': result.type === 'report'
                                         }" style="width: 40px; height: 40px;">
                                        <i class="bi text-white" :class="{
                                               'bi-person': result.type === 'member',
                                               'bi-arrow-left-right': result.type === 'transaction',
                                               'bi-list-ul': result.type === 'account',
                                               'bi-graph-up': result.type === 'report'
                                           }"></i>
                                    </div>
                                </div>

                                <!-- Result Content -->
                                <div class="flex-grow-1">
                                    <h6 class="mb-1" x-text="result.title"></h6>
                                    <p class="mb-1 small" x-text="result.description"></p>
                                    <div class="d-flex align-items-center">
                                        <span class="badge me-2" :class="{
                                                  'bg-success': result.type === 'member',
                                                  'bg-info': result.type === 'transaction',
                                                  'bg-warning': result.type === 'account',
                                                  'bg-primary': result.type === 'report'
                                              }" x-text="getTypeLabel(result.type)"></span>
                                        <small class="text-muted" x-text="result.meta"></small>
                                    </div>
                                </div>

                                <!-- Result Actions -->
                                <div class="flex-shrink-0">
                                    <i class="bi bi-arrow-right"></i>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- No Results -->
                <div x-show="!loading && query.length > 0 && results.length === 0" class="text-center py-4">
                    <i class="bi bi-search display-4 text-muted"></i>
                    <p class="mt-2 text-muted">Tidak ada hasil ditemukan untuk "<span x-text="query"></span>"</p>
                    <p class="small text-muted">Coba gunakan kata kunci yang berbeda atau periksa ejaan</p>
                </div>

                <!-- Recent Searches -->
                <div x-show="!loading && query.length === 0 && recentSearches.length > 0" class="recent-searches">
                    <h6 class="text-muted mb-3">Pencarian Terbaru</h6>
                    <template x-for="search in recentSearches" :key="search.id">
                        <div class="d-flex align-items-center justify-content-between p-2 border rounded mb-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-clock-history text-muted me-2"></i>
                                <span x-text="search.query"></span>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary" @click="useRecentSearch(search.query)">
                                <i class="bi bi-arrow-up-left"></i>
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Quick Actions -->
                <div x-show="!loading && query.length === 0" class="quick-actions mt-4">
                    <h6 class="text-muted mb-3">Aksi Cepat</h6>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <a href="{{ route('members.create') }}" class="btn btn-outline-primary w-100">
                                <i class="bi bi-person-plus me-2"></i>
                                Tambah Anggota
                            </a>
                        </div>
                        <div class="col-6 mb-2">
                            <a href="{{ route('savings.create') }}" class="btn btn-outline-success w-100">
                                <i class="bi bi-piggy-bank me-2"></i>
                                Buka Simpanan
                            </a>
                        </div>
                        <div class="col-6 mb-2">
                            <a href="{{ route('loans.create') }}" class="btn btn-outline-info w-100">
                                <i class="bi bi-cash-stack me-2"></i>
                                Pinjaman Baru
                            </a>
                        </div>
                        <div class="col-6 mb-2">
                            <a href="{{ route('reports.index') }}" class="btn btn-outline-warning w-100">
                                <i class="bi bi-graph-up me-2"></i>
                                Lihat Laporan
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer border-0 pt-0">
                <div class="d-flex justify-content-between w-100">
                    <div class="text-muted small">
                        <kbd>↑</kbd> <kbd>↓</kbd> untuk navigasi, <kbd>Enter</kbd> untuk pilih, <kbd>Esc</kbd> untuk
                        tutup
                    </div>
                    <div class="text-muted small">
                        <span x-show="results.length > 0" x-text="`${results.length} hasil ditemukan`"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function globalSearch() {
    return {
        query: '',
        searchType: 'all',
        results: [],
        recentSearches: [],
        loading: false,
        selectedIndex: -1,

        init() {
            this.loadRecentSearches();
        },

        async search() {
            if (this.query.length < 2) {
                this.results = [];
                return;
            }

            this.loading = true;
            this.selectedIndex = -1;

            try {
                const response = await fetch(`/api/search?q=${encodeURIComponent(this.query)}&type=${this.searchType}`);
                const data = await response.json();
                this.results = data.results || [];

                // Save to recent searches
                this.saveRecentSearch(this.query);
            } catch (error) {
                console.error('Search error:', error);
                this.results = [];
            } finally {
                this.loading = false;
            }
        },

        selectResult(result) {
            window.location.href = result.url;
        },

        selectFirst() {
            if (this.results.length > 0) {
                this.selectResult(this.results[0]);
            }
        },

        navigateDown() {
            if (this.selectedIndex < this.results.length - 1) {
                this.selectedIndex++;
            }
        },

        navigateUp() {
            if (this.selectedIndex > 0) {
                this.selectedIndex--;
            }
        },

        getTypeLabel(type) {
            const labels = {
                'member': 'Anggota',
                'transaction': 'Transaksi',
                'account': 'Akun',
                'report': 'Laporan'
            };
            return labels[type] || type;
        },

        saveRecentSearch(query) {
            let recent = JSON.parse(localStorage.getItem('recentSearches') || '[]');
            recent = recent.filter(item => item.query !== query);
            recent.unshift({ id: Date.now(), query: query, timestamp: Date.now() });
            recent = recent.slice(0, 5); // Keep only 5 recent searches
            localStorage.setItem('recentSearches', JSON.stringify(recent));
            this.recentSearches = recent;
        },

        loadRecentSearches() {
            this.recentSearches = JSON.parse(localStorage.getItem('recentSearches') || '[]');
        },

        useRecentSearch(query) {
            this.query = query;
            this.search();
        }
    }
}
</script>
@endpush

@push('styles')
<style>
    .search-result-item {
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .search-result-item:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .result-icon {
        transition: all 0.2s ease;
    }

    .search-result-item:hover .result-icon {
        transform: scale(1.1);
    }

    .recent-searches .btn {
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .recent-searches .d-flex:hover .btn {
        opacity: 1;
    }

    .quick-actions .btn {
        transition: all 0.2s ease;
    }

    .quick-actions .btn:hover {
        transform: translateY(-2px);
    }

    kbd {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 3px;
        padding: 2px 4px;
        font-size: 0.75rem;
    }
</style>
@endpush
