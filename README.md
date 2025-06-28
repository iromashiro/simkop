# Backend - Sistem Notifikasi Aplikasi Pelaporan Keuangan Koperasi

## 📋 Daftar Isi

-   [Overview](#overview)
-   [Fitur](#fitur)
-   [Tech Stack](#tech-stack)
-   [Requirements](#requirements)
-   [Direktori](#direktori)
-   [Instalasi](#instalasi)
-   [Konfigurasi](#konfigurasi)
-   [Struktur Database](#struktur-database)
-   [API Documentation](#api-documentation)
-   [Event System](#event-system)
-   [Testing](#testing)
-   [Deployment](#deployment)
-   [Contributing](#contributing)
-   [License](#license)

## Overview

Backend sistem notifikasi untuk Aplikasi Pelaporan Keuangan Koperasi yang dibangun dengan Laravel. Sistem ini menyediakan notifikasi in-app yang sederhana namun efektif untuk mendukung workflow pelaporan keuangan antara Admin Koperasi dan Admin Dinas.

### Arsitektur Simplified

Sistem ini menggunakan pendekatan **simplified architecture** yang mengutamakan:

-   ✅ Direct model access (tanpa repository pattern)
-   ✅ Simple service layer dengan minimal abstraction
-   ✅ Direct JSON response (tanpa resource transformation)
-   ✅ Database-driven notification storage
-   ✅ HTTP polling untuk update notifikasi

## Fitur

### Core Features

-   🔔 **In-App Notifications** - Notifikasi real-time tanpa dependensi eksternal
-   📊 **Event-Driven Architecture** - Notifikasi otomatis berdasarkan event sistem
-   🔒 **User-Specific Notifications** - Setiap user hanya melihat notifikasinya sendiri
-   📱 **Responsive API** - RESTful API dengan rate limiting
-   🗑️ **Auto-Purge** - Pembersihan otomatis notifikasi lama

### Notification Types

1. **report_submitted** - Notifikasi saat koperasi mengirim laporan
2. **report_approved** - Notifikasi saat laporan disetujui
3. **report_rejected** - Notifikasi saat laporan ditolak

## Tech Stack

-   **Framework**: Laravel 10.x
-   **Database**: PostgreSQL 14+
-   **Authentication**: Laravel Sanctum
-   **Queue**: Sync (dapat diupgrade ke Redis)
-   **Cache**: File-based (dapat diupgrade)
-   **PHP**: 8.1+

## Requirements

### System Requirements

```bash
PHP >= 8.1
PostgreSQL >= 14
Composer >= 2.0
Node.js >= 16 (untuk asset compilation)
```

### PHP Extensions

```bash
php-mbstring
php-xml
php-pgsql
php-json
php-bcmath
php-tokenizer
```

## Direktori

```direktori
simkop/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/                    # Laravel Breeze (existing)
│   │   │   ├── Admin/
│   │   │   │   ├── CooperativeController.php
│   │   │   │   ├── UserManagementController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   └── ReportApprovalController.php
│   │   │   ├── Financial/
│   │   │   │   ├── BalanceSheetController.php
│   │   │   │   ├── IncomeStatementController.php
│   │   │   │   ├── EquityChangesController.php
│   │   │   │   ├── CashFlowController.php
│   │   │   │   ├── MemberSavingsController.php
│   │   │   │   ├── MemberReceivablesController.php
│   │   │   │   ├── NPLReceivablesController.php
│   │   │   │   ├── SHUDistributionController.php
│   │   │   │   ├── BudgetPlanController.php
│   │   │   │   └── NotesToFinancialController.php
│   │   │   ├── Reports/
│   │   │   │   ├── PDFExportController.php
│   │   │   │   ├── ExcelExportController.php
│   │   │   │   └── BatchExportController.php
│   │   │   ├── NotificationController.php
│   │   │   └── DashboardController.php
│   │   ├── Requests/
│   │   │   ├── Financial/
│   │   │   │   ├── BalanceSheetRequest.php
│   │   │   │   ├── IncomeStatementRequest.php
│   │   │   │   ├── EquityChangesRequest.php
│   │   │   │   ├── CashFlowRequest.php
│   │   │   │   ├── MemberSavingsRequest.php
│   │   │   │   ├── MemberReceivablesRequest.php
│   │   │   │   ├── NPLReceivablesRequest.php
│   │   │   │   ├── SHUDistributionRequest.php
│   │   │   │   └── BudgetPlanRequest.php
│   │   │   ├── Admin/
│   │   │   │   ├── CooperativeRequest.php
│   │   │   │   └── UserManagementRequest.php
│   │   │   └── NotificationRequest.php
│   │   ├── Middleware/
│   │   │   ├── FinancialAuditMiddleware.php
│   │   │   ├── RoleMiddleware.php
│   │   │   └── CooperativeAccessMiddleware.php
│   │   └── Resources/
│   │       ├── Financial/
│   │       │   ├── BalanceSheetResource.php
│   │       │   ├── IncomeStatementResource.php
│   │       │   └── [other financial resources]
│   │       └── NotificationResource.php
│   ├── Models/
│   │   ├── User.php                     # Enhanced with roles
│   │   ├── Cooperative.php              # Enhanced with relationships
│   │   ├── Notification.php             # Simple notification system
│   │   ├── AuditLog.php                 # Audit trail
│   │   ├── Financial/
│   │   │   ├── FinancialReport.php      # Main report model
│   │   │   ├── BalanceSheetAccount.php
│   │   │   ├── IncomeStatementAccount.php
│   │   │   ├── EquityChange.php
│   │   │   ├── CashFlowActivity.php
│   │   │   ├── MemberSaving.php
│   │   │   ├── MemberReceivable.php
│   │   │   ├── NonPerformingReceivable.php
│   │   │   ├── SHUDistribution.php
│   │   │   └── BudgetPlan.php
│   │   └── Traits/
│   │       ├── HasAuditLog.php
│   │       └── BelongsToCooperative.php
│   ├── Services/
│   │   ├── Financial/
│   │   │   ├── ReportGenerationService.php
│   │   │   ├── FinancialValidationService.php
│   │   │   ├── BalanceSheetService.php
│   │   │   ├── IncomeStatementService.php
│   │   │   ├── EquityChangesService.php
│   │   │   ├── CashFlowService.php
│   │   │   └── YearOverYearComparisonService.php
│   │   ├── Export/
│   │   │   ├── PDFExportService.php
│   │   │   ├── ExcelExportService.php
│   │   │   └── BatchExportService.php
│   │   ├── NotificationService.php
│   │   ├── AuditLogService.php
│   │   └── DashboardAnalyticsService.php
│   ├── Observers/
│   │   ├── FinancialReportObserver.php
│   │   └── UserObserver.php
│   ├── Jobs/
│   │   ├── GenerateReportPDF.php
│   │   ├── BatchExportReports.php
│   │   └── SendNotification.php
│   ├── Rules/
│   │   ├── BalanceSheetEquation.php
│   │   ├── CashFlowConsistency.php
│   │   └── FinancialDataIntegrity.php
│   └── Providers/
│       ├── AppServiceProvider.php       # Enhanced
│       ├── AuthServiceProvider.php      # Enhanced with permissions
│       └── EventServiceProvider.php     # Enhanced with observers
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php           # Existing
│   │   ├── 0001_01_01_000001_create_cache_table.php           # Existing
│   │   ├── 0001_01_01_000002_create_jobs_table.php            # Existing
│   │   ├── 2025_06_27_200416_create_permission_tables.php     # Existing
│   │   ├── 2025_06_28_000001_create_cooperatives_table.php
│   │   ├── 2025_06_28_000002_add_cooperative_id_to_users_table.php
│   │   ├── 2025_06_28_000003_create_notifications_table.php
│   │   ├── 2025_06_28_000004_create_audit_logs_table.php
│   │   ├── 2025_06_28_000005_create_financial_reports_table.php
│   │   ├── 2025_06_28_000006_create_balance_sheet_accounts_table.php
│   │   ├── 2025_06_28_000007_create_income_statement_accounts_table.php
│   │   ├── 2025_06_28_000008_create_equity_changes_table.php
│   │   ├── 2025_06_28_000009_create_cash_flow_activities_table.php
│   │   ├── 2025_06_28_000010_create_member_savings_table.php
│   │   ├── 2025_06_28_000011_create_member_receivables_table.php
│   │   ├── 2025_06_28_000012_create_non_performing_receivables_table.php
│   │   ├── 2025_06_28_000013_create_shu_distribution_table.php
│   │   ├── 2025_06_28_000014_create_budget_plans_table.php
│   │   └── 2025_06_28_000015_create_indexes.php
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   ├── AdminDinasSeeder.php         # Existing
│   │   ├── RolePermissionSeeder.php
│   │   ├── CooperativeSeeder.php
│   │   └── SampleDataSeeder.php
│   └── factories/
│       ├── CooperativeFactory.php
│       └── Financial/
│           ├── BalanceSheetAccountFactory.php
│           └── [other factories for testing]
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── app.blade.php            # Enhanced with notifications
│   │   │   ├── guest.blade.php          # Existing
│   │   │   └── navigation.blade.php     # Enhanced with role-based menu
│   │   ├── dashboard/
│   │   │   ├── admin-dinas.blade.php
│   │   │   └── admin-koperasi.blade.php
│   │   ├── admin/
│   │   │   ├── cooperatives/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   ├── edit.blade.php
│   │   │   │   └── show.blade.php
│   │   │   ├── users/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   └── edit.blade.php
│   │   │   └── reports/
│   │   │       ├── approval.blade.php
│   │   │       └── analytics.blade.php
│   │   ├── financial/
│   │   │   ├── balance-sheet/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   └── edit.blade.php
│   │   │   ├── income-statement/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   └── edit.blade.php
│   │   │   ├── equity-changes/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   └── edit.blade.php
│   │   │   ├── cash-flow/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   └── edit.blade.php
│   │   │   ├── member-savings/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   └── edit.blade.php
│   │   │   ├── member-receivables/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   └── edit.blade.php
│   │   │   ├── npl-receivables/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   └── edit.blade.php
│   │   │   ├── shu-distribution/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   └── edit.blade.php
│   │   │   ├── budget-plan/
│   │   │   │   ├── index.blade.php
│   │   │   │   ├── create.blade.php
│   │   │   │   └── edit.blade.php
│   │   │   └── notes/
│   │   │       ├── index.blade.php
│   │   │       ├── create.blade.php
│   │   │       └── edit.blade.php
│   │   ├── reports/
│   │   │   ├── pdf/
│   │   │   │   ├── balance_sheet.blade.php
│   │   │   │   ├── income_statement.blade.php
│   │   │   │   ├── equity_changes.blade.php
│   │   │   │   ├── cash_flow.blade.php
│   │   │   │   ├── member_savings.blade.php
│   │   │   │   ├── member_receivables.blade.php
│   │   │   │   ├── npl_receivables.blade.php
│   │   │   │   ├── shu_distribution.blade.php
│   │   │   │   ├── budget_plan.blade.php
│   │   │   │   ├── notes_to_financial.blade.php
│   │   │   │   └── complete_report.blade.php
│   │   │   ├── export/
│   │   │   │   ├── index.blade.php
│   │   │   │   └── batch.blade.php
│   │   │   └── preview/
│   │   │       ├── balance_sheet.blade.php
│   │   │       ├── income_statement.blade.php
│   │   │       └── [other preview templates]
│   │   ├── notifications/
│   │   │   ├── index.blade.php
│   │   │   └── partials/
│   │   │       ├── notification-bell.blade.php
│   │   │       └── notification-item.blade.php
│   │   ├── components/
│   │   │   ├── financial/
│   │   │   │   ├── account-input.blade.php
│   │   │   │   ├── amount-input.blade.php
│   │   │   │   ├── year-comparison.blade.php
│   │   │   │   └── validation-errors.blade.php
│   │   │   ├── forms/
│   │   │   │   ├── text-input.blade.php
│   │   │   │   ├── select-input.blade.php
│   │   │   │   └── textarea-input.blade.php
│   │   │   └── ui/
│   │   │       ├── alert.blade.php
│   │   │       ├── modal.blade.php
│   │   │       └── loading-spinner.blade.php
│   │   └── auth/                        # Existing Laravel Breeze
│   ├── css/
│   │   ├── app.css                      # Enhanced with custom styles
│   │   └── financial-forms.css          # Specific styling for forms
│   └── js/
│       ├── app.js                       # Enhanced with Alpine.js
│       ├── financial-forms.js           # Form interactions
│       ├── notifications.js             # Notification handling
│       └── dashboard-charts.js          # Dashboard analytics
├── routes/
│   ├── web.php                          # Enhanced with all routes
│   ├── auth.php                         # Existing Laravel Breeze
│   └── api.php                          # For AJAX endpoints
├── config/
│   ├── app.php                          # Enhanced
│   ├── database.php                     # PostgreSQL config
│   ├── permission.php                   # Spatie permission config
│   ├── dompdf.php                       # PDF generation config
│   └── excel.php                        # Excel export config
├── storage/
│   ├── app/
│   │   ├── public/
│   │   │   ├── exports/                 # Generated reports
│   │   │   └── logos/                   # Cooperative logos
│   │   ├── exports/                     # Temporary export files
│   │   └── logs/                        # Application logs
│   ├── framework/
│   └── logs/
├── tests/
│   ├── Feature/
│   │   ├── Auth/                        # Existing
│   │   ├── Admin/
│   │   │   ├── CooperativeManagementTest.php
│   │   │   └── UserManagementTest.php
│   │   ├── Financial/
│   │   │   ├── BalanceSheetTest.php
│   │   │   ├── IncomeStatementTest.php
│   │   │   ├── EquityChangesTest.php
│   │   │   ├── CashFlowTest.php
│   │   │   ├── MemberSavingsTest.php
│   │   │   ├── MemberReceivablesTest.php
│   │   │   ├── NPLReceivablesTest.php
│   │   │   ├── SHUDistributionTest.php
│   │   │   └── BudgetPlanTest.php
│   │   ├── Reports/
│   │   │   ├── PDFExportTest.php
│   │   │   ├── ExcelExportTest.php
│   │   │   └── BatchExportTest.php
│   │   ├── NotificationTest.php
│   │   └── DashboardTest.php
│   ├── Unit/
│   │   ├── Models/
│   │   │   ├── UserTest.php
│   │   │   ├── CooperativeTest.php
│   │   │   └── Financial/
│   │   │       ├── BalanceSheetAccountTest.php
│   │   │       └── [other model tests]
│   │   ├── Services/
│   │   │   ├── ReportGenerationServiceTest.php
│   │   │   ├── FinancialValidationServiceTest.php
│   │   │   └── NotificationServiceTest.php
│   │   └── Rules/
│   │       ├── BalanceSheetEquationTest.php
│   │       └── CashFlowConsistencyTest.php
│   └── TestCase.php
├── docs/                                # Documentation
│   ├── SAD.md                           # System Architecture Design
│   ├── SRS.md                           # System Requirements Specification
│   ├── API.md                           # API Documentation
│   ├── DEPLOYMENT.md                    # Deployment Guide
│   └── USER_MANUAL.md                   # User Manual
├── .env.example                         # Enhanced with all configs
├── composer.json                        # Enhanced with required packages
├── package.json                         # Enhanced with frontend deps
├── artisan                              # Enhanced with custom commands
└── README.md                            # Project documentation
```

## Instalasi

### 1. Clone Repository

```bash
git clone https://github.com/your-org/koperasi-notification-backend.git
cd koperasi-notification-backend
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure Database

Edit `.env` file:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=koperasi_notifications
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 5. Run Migrations

```bash
php artisan migrate
```

### 6. Setup Authentication

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

### 7. Configure Rate Limiting

Di `app/Providers/RouteServiceProvider.php`:

```php
RateLimiter::for('notifications', function (Request $request) {
    return Limit::perMinute(12)->by($request->user()?->id ?: $request->ip());
});
```

### 8. Register Event Subscriber

Di `app/Providers/EventServiceProvider.php`:

```php
protected $subscribe = [
    \App\Listeners\NotificationEventSubscriber::class,
];
```

## Konfigurasi

### Environment Variables

```env
# App Configuration
APP_NAME="Koperasi Notification System"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Notification Settings
NOTIFICATION_POLLING_INTERVAL=5000 # dalam milliseconds
NOTIFICATION_RETENTION_DAYS=365
NOTIFICATION_PAGE_LIMIT=50

# Cache Configuration
CACHE_DRIVER=file
SESSION_DRIVER=file

# Queue Configuration (optional upgrade)
QUEUE_CONNECTION=sync
```

### Laravel Configuration

```php
// config/notifications.php
return [
    'polling_interval' => env('NOTIFICATION_POLLING_INTERVAL', 5000),
    'retention_days' => env('NOTIFICATION_RETENTION_DAYS', 365),
    'page_limit' => env('NOTIFICATION_PAGE_LIMIT', 50),
];
```

## Struktur Database

### Notifications Table

```sql
CREATE TABLE notifications (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id),
    cooperative_id BIGINT REFERENCES cooperatives(id),
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Performance indexes
CREATE INDEX idx_notifications_user_unread
ON notifications(user_id, is_read, created_at);
```

## API Documentation

### Authentication

Semua endpoint memerlukan authentication token:

```http
Authorization: Bearer {token}
```

### Endpoints

#### 1. Get Notifications

```http
GET /api/notifications?limit=50
```

**Response:**

```json
{
    "data": [
        {
            "id": 123,
            "type": "report_submitted",
            "title": "Laporan baru dikirim",
            "message": "Laporan Neraca tahun 2024 telah dikirim oleh koperasi KPN Kesatuan",
            "is_read": false,
            "created_at": "5 minutes ago",
            "created_at_iso": "2024-06-15T14:30:00+07:00",
            "cooperative": {
                "id": 42,
                "name": "KPN Kesatuan"
            }
        }
    ]
}
```

#### 2. Get Unread Count

```http
GET /api/notifications/count
```

**Response:**

```json
{
    "count": 5
}
```

#### 3. Mark as Read

```http
POST /api/notifications/{id}/read
X-CSRF-TOKEN: {csrf_token}
```

**Response:**

```json
{
    "success": true
}
```

### Error Responses

```json
{
    "error": "The notification could not be found."
}
```

**Status Codes:**

-   `200` - Success
-   `401` - Unauthorized
-   `404` - Not Found
-   `429` - Too Many Requests

## Event System

### Available Events

#### 1. ReportSubmittedEvent

```php
event(new ReportSubmittedEvent($reportId, $cooperativeId));
```

#### 2. ReportApprovedEvent

```php
event(new ReportApprovedEvent($reportId, $cooperativeId));
```

#### 3. ReportRejectedEvent

```php
event(new ReportRejectedEvent($reportId, $cooperativeId, $reason));
```

### Creating Custom Notifications

```php
// In your service/controller
use App\Services\NotificationService;

class YourService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function someAction()
    {
        // Direct notification creation
        $this->notificationService->createNotification(
            $userId,
            $cooperativeId,
            'custom_type',
            'Custom Title',
            'Custom message content'
        );
    }
}
```

## Testing

### Run All Tests

```bash
php artisan test
```

### Run Specific Test Suite

```bash
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

### Test Structure

```
tests/
├── Feature/
│   ├── NotificationApiTest.php
│   ├── NotificationEventTest.php
│   └── NotificationWorkflowTest.php
├── Unit/
│   ├── NotificationServiceTest.php
│   ├── NotificationModelTest.php
│   └── NotificationControllerTest.php
```

### Example Test

```php
public function test_user_can_get_own_notifications()
{
    $user = User::factory()->create();
    Notification::factory()->count(5)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
                     ->getJson('/api/notifications');

    $response->assertStatus(200)
             ->assertJsonCount(5, 'data');
}
```

## Deployment

### Production Checklist

1. **Environment Configuration**

```bash
APP_ENV=production
APP_DEBUG=false
```

2. **Optimize Application**

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

3. **Database Optimization**

```bash
# Ensure indexes are created
php artisan migrate --force
```

4. **Setup Cron Jobs**

```bash
# Add to crontab for notification purging
0 2 * * * cd /path/to/project && php artisan notifications:purge >> /dev/null 2>&1
```

5. **Configure Web Server**

**Nginx Configuration:**

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
}
```

### Performance Optimization

1. **Enable OPcache**

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
```

2. **Configure Database Connection Pooling**

```php
// config/database.php
'pgsql' => [
    'driver' => 'pgsql',
    'options' => [
        PDO::ATTR_PERSISTENT => true,
    ],
],
```

### Monitoring

1. **Log Configuration**

```env
LOG_CHANNEL=daily
LOG_LEVEL=error
```

2. **Health Check Endpoint**

```php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});
```

## Contributing

### Development Workflow

1. **Fork & Clone**

```bash
git clone https://github.com/YOUR_USERNAME/koperasi-notification-backend.git
```

2. **Create Feature Branch**

```bash
git checkout -b feature/your-feature-name
```

3. **Make Changes & Test**

```bash
# Make your changes
php artisan test
```

4. **Commit dengan Conventional Commits**

```bash
git commit -m "feat: add notification grouping feature"
git commit -m "fix: resolve notification count cache issue"
git commit -m "docs: update API documentation"
```

5. **Push & Create PR**

```bash
git push origin feature/your-feature-name
```

### Code Standards

-   Follow PSR-12 coding standards
-   Use Laravel best practices
-   Write tests for new features
-   Update documentation

### Commit Message Format

```
type(scope): subject

body

footer
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## Support

-   📧 Email: support@koperasi-app.id
-   📱 Phone: +62 21 1234 5678
-   📖 Documentation: https://docs.koperasi-app.id
-   🐛 Issues: https://github.com/your-org/koperasi-notification-backend/issues

---

**Developed with ❤️ for Koperasi Indonesia**
