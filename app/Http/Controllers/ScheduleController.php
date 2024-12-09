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
}