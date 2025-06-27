<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Member Domain
use App\Domain\Member\Contracts\MemberRepositoryInterface;
use App\Domain\Member\Repositories\MemberRepository;

// Cooperative Domain
use App\Domain\Cooperative\Contracts\CooperativeRepositoryInterface;
use App\Domain\Cooperative\Repositories\CooperativeRepository;

// Accounting Domain
use App\Domain\Accounting\Contracts\AccountRepositoryInterface;
use App\Domain\Accounting\Repositories\AccountRepository;
use App\Domain\Accounting\Contracts\FiscalPeriodRepositoryInterface;
use App\Domain\Accounting\Repositories\FiscalPeriodRepository;
use App\Domain\Accounting\Contracts\JournalEntryRepositoryInterface;
use App\Domain\Accounting\Repositories\JournalEntryRepository;

// Loan Domain
use App\Domain\Loan\Contracts\LoanRepositoryInterface;
use App\Domain\Loan\Repositories\LoanRepository;

// Savings Domain
use App\Domain\Savings\Contracts\SavingsRepositoryInterface;
use App\Domain\Savings\Repositories\SavingsRepository;

/**
 * Domain Service Provider
 *
 * Binds all domain interfaces to concrete implementations
 * Critical for dependency injection to work properly
 *
 * @package App\Providers
 * @author Mateen (Senior Software Engineer)
 */
class DomainServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Member Domain Bindings
        $this->app->bind(
            MemberRepositoryInterface::class,
            MemberRepository::class
        );

        // Cooperative Domain Bindings
        $this->app->bind(
            CooperativeRepositoryInterface::class,
            CooperativeRepository::class
        );

        // Accounting Domain Bindings
        $this->app->bind(
            AccountRepositoryInterface::class,
            AccountRepository::class
        );

        $this->app->bind(
            FiscalPeriodRepositoryInterface::class,
            FiscalPeriodRepository::class
        );

        $this->app->bind(
            JournalEntryRepositoryInterface::class,
            JournalEntryRepository::class
        );

        // Loan Domain Bindings
        $this->app->bind(
            LoanRepositoryInterface::class,
            LoanRepository::class
        );

        // Savings Domain Bindings
        $this->app->bind(
            SavingsRepositoryInterface::class,
            SavingsRepository::class
        );
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Register event listeners if needed
        // Event::listen(MemberCreated::class, SetupMemberAccountsListener::class);
    }
}
