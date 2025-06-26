<?php
// app/Console/Commands/BackupDatabaseCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * PRODUCTION READY: Database backup command with multi-tenant support
 * SRS Reference: Section 4.2 - Data Backup and Recovery Requirements
 */
class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hermes:backup-database
                           {--cooperative= : Backup specific cooperative (optional)}
                           {--type=full : Backup type: full, incremental, or cooperative}
                           {--compress : Compress backup file}
                           {--verify : Verify backup integrity after creation}';

    /**
     * The console command description.
     */
    protected $description = 'Create database backup with multi-tenant support for HERMES system';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);

        try {
            $this->info('🚀 Starting HERMES database backup...');

            // Get command options
            $cooperativeId = $this->option('cooperative');
            $backupType = $this->option('type');
            $shouldCompress = $this->option('compress');
            $shouldVerify = $this->option('verify');

            // Validate backup type
            if (!in_array($backupType, ['full', 'incremental', 'cooperative'])) {
                $this->error('❌ Invalid backup type. Use: full, incremental, or cooperative');
                return Command::FAILURE;
            }

            // Validate cooperative ID if specified
            if ($cooperativeId && !$this->validateCooperative($cooperativeId)) {
                $this->error("❌ Cooperative ID {$cooperativeId} not found");
                return Command::FAILURE;
            }

            // Create backup directory
            $backupDir = $this->createBackupDirectory();

            // Generate backup filename
            $filename = $this->generateBackupFilename($backupType, $cooperativeId);
            $backupPath = "{$backupDir}/{$filename}";

            $this->info("📁 Backup will be saved to: {$backupPath}");

            // Create progress bar
            $progressBar = $this->output->createProgressBar(100);
            $progressBar->start();

            // Perform backup based on type
            switch ($backupType) {
                case 'full':
                    $this->createFullBackup($backupPath, $progressBar);
                    break;
                case 'incremental':
                    $this->createIncrementalBackup($backupPath, $progressBar);
                    break;
                case 'cooperative':
                    $this->createCooperativeBackup($backupPath, $cooperativeId, $progressBar);
                    break;
            }

            $progressBar->finish();
            $this->newLine();

            // Compress backup if requested
            if ($shouldCompress) {
                $this->info('🗜️ Compressing backup...');
                $compressedPath = $this->compressBackup($backupPath);
                $backupPath = $compressedPath;
            }

            // Verify backup if requested
            if ($shouldVerify) {
                $this->info('🔍 Verifying backup integrity...');
                if (!$this->verifyBackup($backupPath)) {
                    $this->error('❌ Backup verification failed');
                    return Command::FAILURE;
                }
                $this->info('✅ Backup verification successful');
            }

            // Calculate execution time and file size
            $executionTime = microtime(true) - $startTime;
            $fileSize = $this->getHumanReadableSize(filesize($backupPath));

            // Log backup completion
            Log::info('Database backup completed', [
                'backup_type' => $backupType,
                'cooperative_id' => $cooperativeId,
                'file_path' => $backupPath,
                'file_size' => $fileSize,
                'execution_time' => $executionTime,
                'compressed' => $shouldCompress,
                'verified' => $shouldVerify,
            ]);

            // Store backup metadata
            $this->storeBackupMetadata($backupPath, $backupType, $cooperativeId, $fileSize, $executionTime);

            $this->info("✅ Backup completed successfully!");
            $this->info("📊 File size: {$fileSize}");
            $this->info("⏱️ Execution time: " . round($executionTime, 2) . " seconds");
            $this->info("📁 Location: {$backupPath}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Database backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error("❌ Backup failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Create full database backup
     */
    private function createFullBackup(string $backupPath, $progressBar): void
    {
        $progressBar->setProgress(10);

        // Get database configuration
        $dbConfig = config('database.connections.' . config('database.default'));

        $progressBar->setProgress(20);

        // Build mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s',
            escapeshellarg($dbConfig['host']),
            escapeshellarg($dbConfig['port']),
            escapeshellarg($dbConfig['username']),
            escapeshellarg($dbConfig['password']),
            escapeshellarg($dbConfig['database']),
            escapeshellarg($backupPath)
        );

        $progressBar->setProgress(30);

        // Execute backup command
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        $progressBar->setProgress(90);

        if ($returnCode !== 0) {
            throw new \Exception('mysqldump command failed with return code: ' . $returnCode);
        }

        $progressBar->setProgress(100);
    }

    /**
     * Create incremental backup (changes since last backup)
     */
    private function createIncrementalBackup(string $backupPath, $progressBar): void
    {
        $progressBar->setProgress(10);

        // Get last backup timestamp
        $lastBackupTime = $this->getLastBackupTimestamp();

        $progressBar->setProgress(20);

        // Get tables with recent changes
        $changedTables = $this->getChangedTables($lastBackupTime);

        $progressBar->setProgress(40);

        if (empty($changedTables)) {
            $this->info('ℹ️ No changes detected since last backup');
            file_put_contents($backupPath, "-- No changes since last backup at {$lastBackupTime}\n");
            $progressBar->setProgress(100);
            return;
        }

        // Create incremental backup
        $this->createTableBackup($backupPath, $changedTables, $lastBackupTime, $progressBar);
    }

    /**
     * Create cooperative-specific backup
     */
    private function createCooperativeBackup(string $backupPath, int $cooperativeId, $progressBar): void
    {
        $progressBar->setProgress(10);

        // Get cooperative data tables
        $cooperativeTables = $this->getCooperativeTables();

        $progressBar->setProgress(20);

        // Start SQL dump
        $sqlDump = "-- HERMES Cooperative Backup for Cooperative ID: {$cooperativeId}\n";
        $sqlDump .= "-- Generated at: " . now()->toDateTimeString() . "\n\n";

        $progressBar->setProgress(30);

        $tableCount = count($cooperativeTables);
        $currentTable = 0;

        foreach ($cooperativeTables as $table) {
            $currentTable++;
            $tableProgress = 30 + (($currentTable / $tableCount) * 60);
            $progressBar->setProgress($tableProgress);

            $this->info("📋 Backing up table: {$table}");

            // Export table structure
            $sqlDump .= $this->getTableStructure($table);

            // Export table data for specific cooperative
            $sqlDump .= $this->getCooperativeTableData($table, $cooperativeId);
        }

        $progressBar->setProgress(95);

        // Write backup file
        file_put_contents($backupPath, $sqlDump);

        $progressBar->setProgress(100);
    }

    /**
     * Get tables that have changed since last backup
     */
    private function getChangedTables(string $lastBackupTime): array
    {
        $changedTables = [];

        // Check audit logs for changes
        $auditChanges = DB::table('audit_logs')
            ->where('created_at', '>', $lastBackupTime)
            ->distinct()
            ->pluck('auditable_type')
            ->toArray();

        // Map model names to table names
        $modelToTable = [
            'App\Domain\Cooperative\Models\Cooperative' => 'cooperatives',
            'App\Domain\User\Models\User' => 'users',
            'App\Domain\Financial\Models\JournalEntry' => 'journal_entries',
            'App\Domain\Financial\Models\JournalLine' => 'journal_lines',
            'App\Domain\Member\Models\Member' => 'members',
            'App\Domain\Member\Models\Savings' => 'savings',
            'App\Domain\Member\Models\Loan' => 'loans',
        ];

        foreach ($auditChanges as $modelClass) {
            if (isset($modelToTable[$modelClass])) {
                $changedTables[] = $modelToTable[$modelClass];
            }
        }

        return array_unique($changedTables);
    }

    /**
     * Get cooperative-specific tables
     */
    private function getCooperativeTables(): array
    {
        return [
            'cooperatives',
            'users',
            'members',
            'savings',
            'loans',
            'loan_payments',
            'accounts',
            'journal_entries',
            'journal_lines',
            'fiscal_periods',
            'shu_plans',
            'shu_member_calculations',
            'shu_distributions',
            'budgets',
            'budget_items',
            'audit_logs',
        ];
    }

    /**
     * Get table structure SQL
     */
    private function getTableStructure(string $table): string
    {
        $result = DB::select("SHOW CREATE TABLE `{$table}`");
        $createStatement = $result[0]->{'Create Table'};

        return "\n-- Table structure for table `{$table}`\n" .
            "DROP TABLE IF EXISTS `{$table}`;\n" .
            $createStatement . ";\n\n";
    }

    /**
     * Get cooperative-specific table data
     */
    private function getCooperativeTableData(string $table, int $cooperativeId): string
    {
        $sql = "-- Data for table `{$table}` (Cooperative ID: {$cooperativeId})\n";

        // Build WHERE clause based on table
        $whereClause = $this->buildCooperativeWhereClause($table, $cooperativeId);

        if (!$whereClause) {
            // Table doesn't have cooperative_id, skip or include all
            if (in_array($table, ['cooperatives'])) {
                $whereClause = "WHERE id = {$cooperativeId}";
            } else {
                return $sql . "-- Skipped: No cooperative relationship\n\n";
            }
        }

        // Get data
        $query = "SELECT * FROM `{$table}` {$whereClause}";
        $rows = DB::select($query);

        if (empty($rows)) {
            return $sql . "-- No data found\n\n";
        }

        // Convert to INSERT statements
        foreach ($rows as $row) {
            $columns = array_keys((array) $row);
            $values = array_values((array) $row);

            $escapedValues = array_map(function ($value) {
                return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
            }, $values);

            $sql .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $escapedValues) . ");\n";
        }

        return $sql . "\n";
    }

    /**
     * Build WHERE clause for cooperative filtering
     */
    private function buildCooperativeWhereClause(string $table, int $cooperativeId): string
    {
        $cooperativeRelations = [
            'users' => 'cooperative_id',
            'members' => 'cooperative_id',
            'savings' => 'cooperative_id',
            'loans' => 'cooperative_id',
            'loan_payments' => 'cooperative_id',
            'accounts' => 'cooperative_id',
            'journal_entries' => 'cooperative_id',
            'journal_lines' => 'cooperative_id',
            'fiscal_periods' => 'cooperative_id',
            'shu_plans' => 'cooperative_id',
            'shu_member_calculations' => 'cooperative_id',
            'shu_distributions' => 'cooperative_id',
            'budgets' => 'cooperative_id',
            'budget_items' => 'cooperative_id',
            'audit_logs' => 'cooperative_id',
        ];

        if (isset($cooperativeRelations[$table])) {
            return "WHERE {$cooperativeRelations[$table]} = {$cooperativeId}";
        }

        return '';
    }

    /**
     * Create backup for specific tables
     */
    private function createTableBackup(string $backupPath, array $tables, string $since, $progressBar): void
    {
        $sqlDump = "-- HERMES Incremental Backup\n";
        $sqlDump .= "-- Changes since: {$since}\n";
        $sqlDump .= "-- Generated at: " . now()->toDateTimeString() . "\n\n";

        $tableCount = count($tables);
        $currentTable = 0;

        foreach ($tables as $table) {
            $currentTable++;
            $tableProgress = 40 + (($currentTable / $tableCount) * 50);
            $progressBar->setProgress($tableProgress);

            $sqlDump .= $this->getTableStructure($table);
            $sqlDump .= $this->getIncrementalTableData($table, $since);
        }

        file_put_contents($backupPath, $sqlDump);
    }

    /**
     * Get incremental table data
     */
    private function getIncrementalTableData(string $table, string $since): string
    {
        $sql = "-- Incremental data for table `{$table}`\n";

        // Try to find updated_at or created_at column
        $timeColumn = $this->getTimeColumn($table);

        if (!$timeColumn) {
            return $sql . "-- Skipped: No timestamp column found\n\n";
        }

        $query = "SELECT * FROM `{$table}` WHERE `{$timeColumn}` > '{$since}'";
        $rows = DB::select($query);

        if (empty($rows)) {
            return $sql . "-- No changes found\n\n";
        }

        foreach ($rows as $row) {
            $columns = array_keys((array) $row);
            $values = array_values((array) $row);

            $escapedValues = array_map(function ($value) {
                return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
            }, $values);

            $sql .= "REPLACE INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $escapedValues) . ");\n";
        }

        return $sql . "\n";
    }

    /**
     * Get timestamp column for table
     */
    private function getTimeColumn(string $table): ?string
    {
        $columns = DB::getSchemaBuilder()->getColumnListing($table);

        if (in_array('updated_at', $columns)) {
            return 'updated_at';
        }

        if (in_array('created_at', $columns)) {
            return 'created_at';
        }

        return null;
    }

    /**
     * Compress backup file
     */
    private function compressBackup(string $backupPath): string
    {
        $compressedPath = $backupPath . '.gz';

        $command = "gzip -c {$backupPath} > {$compressedPath}";
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Backup compression failed');
        }

        // Remove original file
        unlink($backupPath);

        return $compressedPath;
    }

    /**
     * Verify backup integrity
     */
    private function verifyBackup(string $backupPath): bool
    {
        // Check if file exists and is readable
        if (!file_exists($backupPath) || !is_readable($backupPath)) {
            return false;
        }

        // Check file size
        if (filesize($backupPath) < 100) { // Minimum 100 bytes
            return false;
        }

        // For compressed files, test decompression
        if (str_ends_with($backupPath, '.gz')) {
            $testCommand = "gzip -t {$backupPath}";
            exec($testCommand, $output, $returnCode);
            return $returnCode === 0;
        }

        // For SQL files, check basic structure
        $content = file_get_contents($backupPath, false, null, 0, 1000);
        return str_contains($content, 'HERMES') || str_contains($content, 'CREATE TABLE');
    }

    /**
     * Store backup metadata
     */
    private function storeBackupMetadata(string $backupPath, string $type, ?int $cooperativeId, string $fileSize, float $executionTime): void
    {
        DB::table('backup_logs')->insert([
            'backup_type' => $type,
            'cooperative_id' => $cooperativeId,
            'file_path' => $backupPath,
            'file_size' => $fileSize,
            'execution_time' => $executionTime,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Validate cooperative exists
     */
    private function validateCooperative(int $cooperativeId): bool
    {
        return DB::table('cooperatives')->where('id', $cooperativeId)->exists();
    }

    /**
     * Create backup directory
     */
    private function createBackupDirectory(): string
    {
        $backupDir = storage_path('app/backups/' . date('Y/m'));

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        return $backupDir;
    }

    /**
     * Generate backup filename
     */
    private function generateBackupFilename(string $type, ?int $cooperativeId): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $suffix = $cooperativeId ? "_coop_{$cooperativeId}" : '';

        return "hermes_{$type}_backup_{$timestamp}{$suffix}.sql";
    }

    /**
     * Get last backup timestamp
     */
    private function getLastBackupTimestamp(): string
    {
        $lastBackup = DB::table('backup_logs')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->first();

        return $lastBackup ? $lastBackup->created_at : '1970-01-01 00:00:00';
    }

    /**
     * Get human readable file size
     */
    private function getHumanReadableSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }
}
