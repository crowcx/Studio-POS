<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tablesToBackup = ['products', 'categories', 'transactions', 'transaction_items', 'users', 'audit_logs'];
        
        $tempDir = storage_path('app/temp_backup_' . time());
        \Illuminate\Support\Facades\File::makeDirectory($tempDir, 0755, true);

        // Fetch Data and save to JSON
        foreach ($tablesToBackup as $table) {
            if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                $data = \Illuminate\Support\Facades\DB::table($table)->get();
                \Illuminate\Support\Facades\File::put($tempDir . '/' . $table . '.json', json_encode($data));
            }
        }

        // Add metadata
        $batch = \Illuminate\Support\Facades\DB::table('migrations')->max('batch') ?? 0;
        $metadata = [
            'app_version' => config('app.version'),
            'migration_batch' => $batch,
            'tables' => $tablesToBackup,
            'created_at' => now()->toDateTimeString()
        ];
        \Illuminate\Support\Facades\File::put($tempDir . '/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));

        // Create ZIP
        $zipFileName = 'backup_v' . config('app.version') . '_' . date('Ymd_His') . '.zip';
        $zipPath = storage_path('app/backups/' . $zipFileName);
        
        if (!\Illuminate\Support\Facades\Storage::disk('local')->exists('backups')) {
            \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory('backups');
        }

        $zip = new \ZipArchive;
        if ($zip->open($zipPath, \ZipArchive::CREATE) === TRUE) {
            $files = \Illuminate\Support\Facades\File::files($tempDir);
            foreach ($files as $file) {
                $zip->addFile($file->getPathname(), $file->getFilename());
            }
            $zip->close();
            $this->info('Zip created at ' . $zipPath);
        } else {
            $this->error('Failed to create zip at ' . $zipPath);
        }

        // Cleanup
        \Illuminate\Support\Facades\File::deleteDirectory($tempDir);
    }
}
