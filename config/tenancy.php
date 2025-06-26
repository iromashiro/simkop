<?php
// config/tenancy.php - Enhanced with security settings
return [
    'tenant_model' => App\Domain\Cooperative\Models\Cooperative::class,

    'tenant_key' => 'cooperative_id',

    'resolution_strategies' => [
        'subdomain' => App\Infrastructure\Tenancy\SubdomainResolver::class,
        'parameter' => App\Infrastructure\Tenancy\ParameterResolver::class,
        'user' => App\Infrastructure\Tenancy\UserResolver::class,
    ],

    'models' => [
        App\Domain\Financial\Models\Account::class,
        App\Domain\Financial\Models\JournalEntry::class,
        App\Domain\Financial\Models\JournalLine::class,
        App\Domain\Financial\Models\FiscalPeriod::class,
        App\Domain\Member\Models\Member::class,
        App\Domain\Member\Models\Savings::class,
        App\Domain\Member\Models\Loan::class,
        App\Domain\Member\Models\LoanPayment::class,
        App\Domain\SHU\Models\ShuPlan::class,
        App\Domain\SHU\Models\ShuMemberCalculation::class,
        App\Domain\Budget\Models\Budget::class,
        App\Domain\Budget\Models\BudgetItem::class,
    ],

    'cache_prefix' => 'tenant',
    'cache_ttl' => 3600,

    // SECURITY: Rate limiting per tenant
    'rate_limit_per_minute' => 100,

    // SECURITY: Maximum tenants per user
    'max_tenants_per_user' => 5,

    // SECURITY: Tenant access validation cache TTL
    'access_cache_ttl' => 300, // 5 minutes
];
