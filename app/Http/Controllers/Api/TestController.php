<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TestController extends Controller
{
    public function testS3(Request $request)
    {
        try {
            $bucket = config('filesystems.disks.s3.bucket');
            $region = config('filesystems.disks.s3.region');
            $key = config('filesystems.disks.s3.key');
            
            if (!$bucket || !$region || !$key) {
                return response()->json([
                    'success' => false,
                    'message' => 'S3 configuration incomplete',
                    'config' => [
                        'bucket' => $bucket ?: 'NOT SET',
                        'region' => $region ?: 'NOT SET',
                        'key' => $key ? 'SET' : 'NOT SET',
                    ],
                ], 400);
            }

            $testContent = 'S3 Test File - ' . now()->toDateTimeString();
            $testPath = 'test/s3-test-' . time() . '.txt';

            Storage::disk('s3')->put($testPath, $testContent);

            $exists = Storage::disk('s3')->exists($testPath);
            $retrieved = Storage::disk('s3')->get($testPath);

            Storage::disk('s3')->delete($testPath);

            return response()->json([
                'success' => true,
                'message' => 'S3 upload test successful!',
                'details' => [
                    'bucket' => $bucket,
                    'region' => $region,
                    'test_file_path' => $testPath,
                    'file_uploaded' => $exists,
                    'file_content_matches' => $retrieved === $testContent,
                    'file_deleted' => true,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'S3 test failed',
                'error' => $e->getMessage(),
                'config' => [
                    'bucket' => config('filesystems.disks.s3.bucket') ?: 'NOT SET',
                    'region' => config('filesystems.disks.s3.region') ?: 'NOT SET',
                ],
            ], 500);
        }
    }
}

