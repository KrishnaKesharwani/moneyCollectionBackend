<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class ScheduleController extends Controller
{
    public function deleteExportFiles()
    {
        $exportPath = storage_path('app/exports');

        // Check if the directory exists
        if (!is_dir($exportPath)) {
            \Log::info('The exports folder does not exist.');
            return response()->json(['message' => 'The exports folder does not exist.'], 404);
        }

        // Get all files in the directory
        $files = new \DirectoryIterator($exportPath);

        $deletedCount = 0;

        // Loop through and delete each file
        foreach ($files as $file) {
            if ($file->isFile()) {
                unlink($file->getPathname());
                $deletedCount++;
            }
        }
        \Log::info("$deletedCount files have been deleted from the exports folder.");
        return response()->json(['message' => "$deletedCount files have been deleted from the exports folder."]);
    }


    public function downloadBackup()
    {
        $dbHost = env('DB_HOST', '127.0.0.1');
        $dbPort = env('DB_PORT', '3307'); // Use your MySQL port
        $dbUser = env('DB_USERNAME');
        $dbPassword = env('DB_PASSWORD');
        $dbName = env('DB_DATABASE');

        // Path to mysqldump (Ensure this path is correct)
        //$mysqlDumpPath = '"D:\\xampp\\mysql\\bin\\mysqldump.exe"';
        $mysqlDumpPath = '/usr/bin/mysqldump'; // Default location on HostGator


        // Backup file name and path
        $backupFileName = "backup_" . date('Y-m-d_H-i-s') . ".sql";
        $backupPath = storage_path("app/backups/{$backupFileName}");

        // Ensure the backups directory exists
        if (!file_exists(storage_path('app/backups'))) {
            mkdir(storage_path('app/backups'), 0777, true);
        }

        // MySQL Dump Command with Port 3307
        $dumpCommand = "{$mysqlDumpPath} -h {$dbHost} -P {$dbPort} -u {$dbUser} --password=\"{$dbPassword}\" {$dbName} > \"{$backupPath}\" 2>&1";

        // Execute the command
        $output = [];
        $returnVar = null;
        exec($dumpCommand, $output, $returnVar);

        if ($returnVar !== 0) {
            return response()->json([
                'error' => 'Backup failed!',
                'details' => implode("\n", $output)
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Backup stored successfully!',
            'file_path' => "storage/app/backups/{$backupFileName}"
        ]);
    }

}