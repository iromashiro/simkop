<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" x-data="helpSystem()" x-init="init()">
            <div class="modal-header">
                <h5 class="modal-title" id="helpModalLabel">
                    <i class="bi bi-question-circle me-2"></i>
                    Bantuan HERMES
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Help Navigation -->
                    <div class="col-md-3">
                        <div class="list-group">
                            <template x-for="category in helpCategories" :key="category.id">
                                <button type="button" class="list-group-item list-group-item-action"
                                    :class="{ 'active': activeCategory === category.id }"
                                    @click="setActiveCategory(category.id)">
                                    <i :class="category.icon" class="me-2"></i>
                                    <span x-text="category.name"></span>
                                </button>
                            </template>
                        </div>

                        <!-- Quick Links -->
                        <div class="mt-4">
                            <h6 class="text-muted">Tautan Cepat</h6>
                            <div class="d-grid gap-2">
                                <a href="/docs/user-manual.pdf" class="btn btn-outline-primary btn-sm" target="_blank">
                                    <i class="bi bi-file-pdf me-2"></i>
                                    Manual Pengguna
                                </a>
                                <a href="/docs/video-tutorials" class="btn btn-outline-success btn-sm" target="_blank">
                                    <i class="bi bi-play-circle me-2"></i>
                                    Video Tutorial
                                </a>
                                <a href="mailto:support@hermes-koperasi.com" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-envelope me-2"></i>
                                    Hubungi Support
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Help Content -->
                    <div class="col-md-9">
                        <!-- Search Help -->
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="text" class="form-control" placeholder="Cari bantuan..."
                                    x-model="searchQuery" @input.debounce.300ms="searchHelp()">
                            </div>
                        </div>

                        <!-- Help Articles -->
                        <div class="help-content">
                            <template x-for="article in filteredArticles" :key="article.id">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <button class="btn btn-link text-start w-100 text-decoration-none"
                                                type="button" @click="toggleArticle(article.id)"
                                                :aria-expanded="expandedArticles.includes(article.id)">
                                                <i class="bi me-2"
                                                    :class="expandedArticles.includes(article.id) ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                                                <span x-text="article.title"></span>
                                            </button>
                                        </h6>
                                    </div>
                                    <div class="card-body" x-show="expandedArticles.includes(article.id)" x-transition>
                                        <div x-html="article.content"></div>

                                        <!-- Article Actions -->
                                        <div class="mt-3 pt-3 border-top">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="text-muted small">Apakah ini membantu?</span>
                                                    <button class="btn btn-sm btn-outline-success ms-2"
                                                        @click="rateArticle(article.id, 'helpful')">
                                                        <i class="bi bi-hand-thumbs-up"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger ms-1"
                                                        @click="rateArticle(article.id, 'not-helpful')">
                                                        <i class="bi bi-hand-thumbs-down"></i>
                                                    </button>
                                                </div>
                                                <div>
                                                    <span class="text-muted small">
                                                        Diperbarui: <span
                                                            x-text="formatDate(article.updated_at)"></span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- No Results -->
                        <div x-show="filteredArticles.length === 0 && searchQuery.length > 0" class="text-center py-4">
                            <i class="bi bi-search display-4 text-muted"></i>
                            <p class="mt-2 text-muted">Tidak ada artikel bantuan ditemukan</p>
                            <button class="btn btn-primary" @click="requestHelp()">
                                <i class="bi bi-plus-circle me-2"></i>
                                Ajukan Pertanyaan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer">
                <div class="d-flex justify-content-between w-100">
                    <div>
                        <span class="text-muted small">
                            Versi HERMES: {{ config('app.version', '1.0.0') }}
                        </span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function helpSystem() {
    return {
        activeCategory: 'getting-started',
        searchQuery: '',
        expandedArticles: [],
        helpCategories: [
            {
                id: 'getting-started',
                name: 'Memulai',
                icon: 'bi-play-circle'
            },
            {
                id: 'members',
                name: 'Manajemen Anggota',
                icon: 'bi-people'
            },
            {
                id: 'savings',
                name: 'Simpanan',
                icon: 'bi-piggy-bank'
            },
            {
                id: 'loans',
                name: 'Pinjaman',
                icon: 'bi-cash-stack'
            },
            {
                id: 'accounting',
                name: 'Akuntansi',
                icon: 'bi-calculator'
            },
            {
                id: 'reports',
                name: 'Laporan',
                icon: 'bi-graph-up'
            },
            {
                id: 'settings',
                name: 'Pengaturan',
                icon: 'bi-gear'
            },
            {
                id: 'troubleshooting',
                name: 'Pemecahan Masalah',
                icon: 'bi-tools'
            }
        ],
        allArticles: [],

        get filteredArticles() {
            let articles = this.allArticles.filter(article =>
                article.category === this.activeCategory
            );

            if (this.searchQuery.length > 0) {
                const query = this.searchQuery.toLowerCase();
                articles = this.allArticles.filter(article =>
                    article.title.toLowerCase().includes(query) ||
                    article.content.toLowerCase().includes(query) ||
                    article.tags.some(tag => tag.toLowerCase().includes(query))
                );
            }

            return articles;
        },

        init() {
            this.loadHelpArticles();
        },

        async loadHelpArticles() {
            try {
                const response = await fetch('/api/help/articles');
                const data = await response.json();
                this.allArticles = data.articles || this.getDefaultArticles();
            } catch (error) {
                console.error('Failed to load help articles:', error);
                this.allArticles = this.getDefaultArticles();
            }
        },

        getDefaultArticles() {
            return [
                {
                    id: 1,
                    category: 'getting-started',
                    title: 'Cara Login ke Sistem HERMES',
                    content: `
                        <p>Untuk masuk ke sistem HERMES, ikuti langkah-langkah berikut:</p>
                        <ol>
                            <li>Buka halaman login HERMES</li>
                            <li>Masukkan email dan password yang telah diberikan</li>
                            <li>Klik tombol "Masuk"</li>
                            <li>Anda akan diarahkan ke dashboard utama</li>
                        </ol>
                        <div class="alert alert-info">
                            <strong>Tips:</strong> Jika lupa password, klik "Lupa Password" untuk reset.
                        </div>
                    `,
                    tags: ['login', 'masuk', 'password'],
                    updated_at: '2024-01-26'
                },
                {
                    id: 2,
                    category: 'members',
                    title: 'Cara Menambah Anggota Baru',
                    content: `
                        <p>Untuk menambahkan anggota baru:</p>
                        <ol>
                            <li>Masuk ke menu "Anggota"</li>
                            <li>Klik tombol "Tambah Anggota"</li>
                            <li>Isi formulir dengan data lengkap anggota</li>
                            <li>Upload dokumen yang diperlukan</li>
                            <li>Klik "Simpan" untuk menyimpan data</li>
                        </ol>
                        <div class="alert alert-warning">
                            <strong>Perhatian:</strong> Pastikan semua data wajib telah diisi dengan benar.
                        </div>
                    `,
                    tags: ['anggota', 'tambah', 'registrasi'],
                    updated_at: '2024-01-26'
                },
                {
                    id: 3,
                    category: 'savings',
                    title: 'Cara Membuka Rekening Simpanan',
                    content: `
                        <p>Untuk membuka rekening simpanan baru:</p>
                        <ol>
                            <li>Pilih menu "Simpanan"</li>
                            <li>Klik "Buka Rekening Baru"</li>
                            <li>Pilih anggota dan produk simpanan</li>
                            <li>Tentukan setoran awal</li>
                            <li>Konfirmasi dan simpan</li>
                        </ol>
                    `,
                    tags: ['simpanan', 'rekening', 'buka'],
                    updated_at: '2024-01-26'
                }
            ];
        },

        setActiveCategory(categoryId) {
            this.activeCategory = categoryId;
            this.searchQuery = '';
        },

        searchHelp() {
            // Search functionality is handled by computed property
        },

        toggleArticle(articleId) {
            const index = this.expandedArticles.indexOf(articleId);
            if (index > -1) {
                this.expandedArticles.splice(index, 1);
            } else {
                this.expandedArticles.push(articleId);
            }
        },

        async rateArticle(articleId, rating) {
            try {
                await fetch('/api/help/rate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        article_id: articleId,
                        rating: rating
                    })
                });

                HERMES.utils.showToast('Terima kasih atas feedback Anda!', 'success');
            } catch (error) {
                console.error('Failed to rate article:', error);
            }
        },

        requestHelp() {
            // Open contact form or redirect to support
            window.open('mailto:support@hermes-koperasi.com?subject=Bantuan HERMES&body=Saya membutuhkan bantuan dengan: ' + encodeURIComponent(this.searchQuery));
        },

        formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('id-ID');
        }
    }
}
</script>
@endpush
