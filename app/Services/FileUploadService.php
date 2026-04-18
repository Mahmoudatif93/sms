<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use AlphaSnow\LaravelFilesystem\Aliyun\OssClientAdapter;
use OSS\OssClient;
use OSS\Core\OssException;

class FileUploadService
{
    /**
     * Upload a file to the specified disk and directory.
     *
     * @param UploadedFile $file
     * @param string $disk
     * @param string|null $directory
     * @return string|false
     */
    public function upload(UploadedFile $file, string $disk = 'oss', string $directory = 'uploads')
    {
        $path = Storage::disk($disk)->putFile($directory, $file);
        return $path;
    }

    public function getFileOss($file)
    {
        // Check if file already exists locally
        if (file_exists($file)) {
            return true;
        }

        $adapter = new OssClientAdapter(Storage::disk("oss"));
        $file_exsit =  $adapter->client()->doesObjectExist(env('OSS_BUCKET'), $file);

        if ($file_exsit) {
            // Create local directory if it doesn't exist
            $localDir = dirname($file);
            if (!is_dir($localDir)) {
                mkdir($localDir, 0755, true);
            }

            // Download the file from OSS to local filesystem
            $options = array(
                OssClient::OSS_FILE_DOWNLOAD => $file,
            );

            try {
                $adapter->client()->getObject(env('OSS_BUCKET'), $file, $options);

                // Verify the file was downloaded successfully
                if (file_exists($file)) {
                    return true;
                } else {
                    return false;
                }
            } catch (OssException $e) {
                // Log the error for debugging
                \Log::error('OSS file download failed: ' . $e->getMessage());
                return false;
            }
        }
        return false;
    }

    public function deleteFileOss($file)
    {
        if ($file == null || $file == "") {
            return false;
        }
        $this->deleteFileServe($file);
        $adapter = new OssClientAdapter(Storage::disk("oss"));

        $fileExsit =  $adapter->client()->doesObjectExist(env('OSS_BUCKET'), $file);

        if ($fileExsit) {
            $adapter->client()->deleteObject(env('OSS_BUCKET'), $file);
        }
        return true;
    }

    public function getFileUrl($file)
    {
        if (file_exists($file)) {
            return url($file);
        }
        return Storage::disk("oss")->temporaryUrl($file, \Carbon\Carbon::now()->addMinutes(30));
    }


    protected function deleteFileServe($file): void
    {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Upload raw file content to the specified disk and directory.
     *
     * @param string $content
     * @param string $disk
     * @param string $path
     * @return string
     */
    public function uploadFromContent(string $content, string $disk = 'oss', string $path = ""): string
    {
        // Store the raw content directly into the storage disk
        Storage::disk($disk)->put($path, $content);
        return $path;
    }

    public function getFilePermanentUrl($file)
    {
        // Check if the file exists locally
        if (file_exists($file)) {
            return url($file);  // Return local URL
        }

        // Generate the public URL if the file is in OSS
        return Storage::disk("oss")->url($file);  // Generate permanent public URL
    }

    public function getSignUrl(string $path, int $expirationInSeconds = 864000)
    {
        try {
            // Initialize the OSS Client
            $ossClient = new OssClient(
                env('OSS_ACCESS_KEY_ID'),
                env('OSS_ACCESS_KEY_SECRET'),
                env('OSS_ENDPOINT')
            );

            $bucket = env('OSS_BUCKET');

            // Generate the signed URL with 'Content-Disposition: inline' for preview
            return $ossClient->signUrl($bucket, $path, $expirationInSeconds, 'GET', [
                OssClient::OSS_HEADERS => [
                    'Content-Disposition' => 'inline',  // Makes the file previewable instead of downloadable
                ],
            ]);
        } catch (OssException $e) {
            // Handle the exception (log it, return null, etc.)
            return null;
        }
    }
}
