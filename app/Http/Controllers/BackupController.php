<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use ZipArchive;
use Illuminate\Support\Facades\File;

class BackupController extends Controller
{
    public function index()
    {
        if (auth()->user()->role !== 'admin') abort(403);
        
        $backups = [];
        $backupsDir = storage_path('app/backups');
        
        if (File::exists($backupsDir)) {
            $files = File::files($backupsDir);
            foreach ($files as $file) {
                if ($file->getExtension() === 'zip') {
                    $backups[] = [
                        'name' => $file->getFilename(),
                        'size' => round($file->getSize() / 1024, 2) . ' KB',
                        'date' => $file->getMTime(),
                        'path' => $file->getPathname()
                    ];
                }
            }
        }
        
        // Sort by date desc
        usort($backups, function($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        return view('settings.backup', compact('backups'));
    }

    public function create(Request $request)
    {
        if (auth()->user()->role !== 'admin') abort(403);
        
        $request->validate([
            'options' => 'required|array',
        ]);
        
        $tablesToBackup = [];
        if (in_array('master', $request->options)) {
            $tablesToBackup = array_merge($tablesToBackup, ['products', 'categories']);
        }
        if (in_array('operational', $request->options)) {
            $tablesToBackup = array_merge($tablesToBackup, ['transactions', 'transaction_items', 'bookings']);
        }
        if (in_array('system', $request->options)) {
            $tablesToBackup = array_merge($tablesToBackup, ['users', 'audit_logs']);
        }
        
        $tablesToBackup = array_unique($tablesToBackup);
        if (empty($tablesToBackup)) {
            return back()->with('error', 'Pilih minimal satu opsi backup.');
        }

        $tempDir = storage_path('app/temp_backup_' . time());
        File::makeDirectory($tempDir, 0755, true);

        // Fetch Data and save to JSON
        foreach ($tablesToBackup as $table) {
            if (Schema::hasTable($table)) {
                $data = DB::table($table)->get();
                File::put($tempDir . '/' . $table . '.json', json_encode($data));
            }
        }

        // Add metadata
        $batch = DB::table('migrations')->max('batch') ?? 0;
        $metadata = [
            'app_version' => config('app.version'),
            'migration_batch' => $batch,
            'tables' => $tablesToBackup,
            'created_at' => now()->toDateTimeString()
        ];
        File::put($tempDir . '/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));

        // Create ZIP
        $zipFileName = 'backup_v' . config('app.version') . '_' . date('Ymd_His') . '.zip';
        $zipPath = storage_path('app/backups/' . $zipFileName);
        
        $backupsDir = storage_path('app/backups');
        if (!File::exists($backupsDir)) {
            File::makeDirectory($backupsDir, 0755, true, true);
        }

        $zip = new ZipArchive;
        $res = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($res === TRUE) {
            $files = File::files($tempDir);
            foreach ($files as $file) {
                $zip->addFile($file->getPathname(), $file->getFilename());
            }
            $zip->close();
        }

        // 10 Slot Rolling Backup Logic
        $allBackups = [];
        $files = File::files($backupsDir);
        foreach ($files as $file) {
            if ($file->getExtension() === 'zip') {
                $allBackups[] = [
                    'path' => $file->getPathname(),
                    'time' => $file->getMTime()
                ];
            }
        }

        // Sort by time descending (newest first)
        usort($allBackups, function($a, $b) {
            return $b['time'] <=> $a['time'];
        });

        // Delete if more than 10
        if (count($allBackups) > 10) {
            $filesToDelete = array_slice($allBackups, 10);
            foreach ($filesToDelete as $oldFile) {
                File::delete($oldFile['path']);
            }
        }

        return back()->with('success', 'Backup berhasil dibuat: ' . $zipFileName);
    }
    
    public function download($fileName)
    {
        if (auth()->user()->role !== 'admin') abort(403);
        
        $fullPath = storage_path('app/backups/' . $fileName);
        
        if (File::exists($fullPath)) {
            return response()->download($fullPath);
        }
        
        return back()->with('error', 'File tidak ditemukan: ' . $fileName);
    }
    
    public function delete($fileName)
    {
        if (auth()->user()->role !== 'admin') abort(403);
        
        $fullPath = storage_path('app/backups/' . $fileName);
        
        if (File::exists($fullPath)) {
            File::delete($fullPath);
            return back()->with('success', 'Backup berhasil dihapus.');
        }
        
        return back()->with('error', 'File tidak ditemukan: ' . $fileName);
    }
    
    public function restore(Request $request)
    {
        if (auth()->user()->role !== 'admin') abort(403);
        
        $request->validate([
            'backup_file' => 'required|file|mimes:zip'
        ]);

        $zipFile = $request->file('backup_file');
        $tempDir = storage_path('app/temp_restore_' . time());
        File::makeDirectory($tempDir, 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($zipFile->getPathname()) === TRUE) {
            $zip->extractTo($tempDir);
            $zip->close();
        } else {
            return back()->with('error', 'Gagal membuka file ZIP.');
        }

        if (!File::exists($tempDir . '/metadata.json')) {
            File::deleteDirectory($tempDir);
            return back()->with('error', 'File metadata.json tidak ditemukan. File backup tidak valid.');
        }

        $metadata = json_decode(File::get($tempDir . '/metadata.json'), true);
        $currentBatch = DB::table('migrations')->max('batch') ?? 0;
        
        try {
            DB::beginTransaction();
            
            // Disable foreign key checks for restore
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            foreach ($metadata['tables'] as $table) {
                // Pastikan file data tersedia dan table ada di database tujuan
                if (File::exists($tempDir . '/' . $table . '.json') && Schema::hasTable($table)) {
                    $jsonContent = File::get($tempDir . '/' . $table . '.json');
                    $data = json_decode($jsonContent, true);
                    
                    // Gunakan delete() alih-alih truncate() karena truncate memicu implicit commit di MySQL yang merusak transaksi
                    DB::table($table)->delete();

                    if (empty($data)) continue;

                    $columns = Schema::getColumnListing($table);
                    $mappedData = [];
                    
                    foreach ($data as $row) {
                        $newRow = [];
                        foreach ($row as $key => $value) {
                            // Mapping: Hanya ambil kolom yang memang ada di database saat ini
                            if (in_array($key, $columns)) {
                                $newRow[$key] = $value;
                            }
                        }
                        $mappedData[] = $newRow;
                    }

                    // Insert in chunks to avoid memory/packet limits
                    foreach (array_chunk($mappedData, 500) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            DB::commit();

            File::deleteDirectory($tempDir);

            // Log action
            \App\Models\AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'sistem restore',
                'details' => ['version' => $metadata['app_version'], 'tables' => $metadata['tables']]
            ]);

            // Clear cache
            cache()->flush();

            $msg = 'Restore selesai dengan sukses.';
            if ($metadata['migration_batch'] < $currentBatch) {
                $msg = 'Restore berhasil. Data versi lama telah diselaraskan ke struktur terbaru.';
            }

            return back()->with('success', $msg);

        } catch (\Exception $e) {
            // Hanya rollback jika transaksi masih aktif (mencegah error "no active transaction")
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            File::deleteDirectory($tempDir);
            return back()->with('error', 'Gagal melakukan restore: ' . $e->getMessage());
        }
    }
}
