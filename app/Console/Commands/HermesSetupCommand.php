<?php
// app/Console/Commands/HermesSetupCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class HermesSetupCommand extends Command
{
    protected $signature = 'hermes:setup {--fresh : Fresh installation}';
    protected $description = 'Setup HERMES system';

    public function handle(): int
    {
        $this->info('🚀 Setting up HERMES System...');

        try {
            // Fresh installation
            if ($this->option('fresh')) {
                $this->warn('⚠️  This will reset all data!');
                if (!$this->confirm('Are you sure?')) {
                    return self::FAILURE;
                }

                $this->call('migrate:fresh');
            } else {
                $this->call('migrate');
            }

            // Seed permissions and roles
            $this->info('📋 Seeding permissions and roles...');
            $this->call('db:seed', ['--class' => 'PermissionSeeder']);
            $this->call('db:seed', ['--class' => 'RoleSeeder']);

            // Create default cooperative if fresh
            if ($this->option('fresh')) {
                $this->info('🏢 Creating default cooperative...');
                $this->call('db:seed', ['--class' => 'CooperativeSeeder']);
            }

            // Clear and warm cache
            $this->info('🔄 Optimizing cache...');
            $this->call('cache:clear');
            $this->call('config:cache');
            $this->call('route:cache');
            $this->call('view:cache');

            // Generate storage link
            $this->info('🔗 Creating storage link...');
            $this->call('storage:link');

            // Queue setup
            $this->info('📬 Setting up queues...');
            $this->call('queue:table');

            $this->info('✅ HERMES setup completed successfully!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Setup failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
