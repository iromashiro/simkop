<?php

namespace App\Domain\Member\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Member\Contracts\MemberRepositoryInterface;
use App\Domain\Member\Repositories\MemberRepository;

/**
 * Member Service Provider
 *
 * Binds member domain interfaces to concrete implementations
 * Registers member-related services and dependencies
 *
 * @package App\Domain\Member\Providers
 * @author Mateen (Senior Software Engineer)
 */
class MemberServiceProvider extends ServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Bind repository interface to implementation
        $this->app->bind(
            MemberRepositoryInterface::class,
            MemberRepository::class
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
