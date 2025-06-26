<?php
// app/Console/Commands/WarmCacheCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Infrastructure\Performance\CacheWarmer;

class WarmCacheCommand extends Command
{
    protected $signature = 'hermes:warm-cache {cooperative_id? : Specific cooperative ID}';
    protected $description = 'Warm application cache';

    public function __construct(
        private CacheWarmer $cacheWarmer
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cooperativeId = $this->argument('cooperative_id');

        try {
            if ($cooperativeId) {
                $this->info("🔥 Warming cache for cooperative: {$cooperativeId}");
                $this->cacheWarmer->warmCooperativeCache($cooperativeId);
            } else {
                $this->info('🔥 Warming cache for all cooperatives...');
                $this->cacheWarmer->warmAllCooperatives();
            }

            $this->info('✅ Cache warming completed!');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Cache warming failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
