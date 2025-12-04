<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class UploadFileToS3Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $s3Path;
    protected $model;
    protected $field;

    public function __construct($filePath, $s3Path, $model = null, $field = null)
    {
        $this->filePath = $filePath;
        $this->s3Path = $s3Path;
        $this->model = $model;
        $this->field = $field;
    }

    public function handle(): void
    {
        if (Storage::disk('local')->exists($this->filePath)) {
            $fileContent = Storage::disk('local')->get($this->filePath);
            Storage::disk('s3')->put($this->s3Path, $fileContent);
            
            if ($this->model && $this->field) {
                $currentValue = $this->model->{$this->field};
                if (is_array($currentValue)) {
                    $updatedPaths = [];
                    foreach ($currentValue as $path) {
                        if ($path === $this->filePath) {
                            $updatedPaths[] = $this->s3Path;
                        } else {
                            $updatedPaths[] = $path;
                        }
                    }
                    $this->model->{$this->field} = $updatedPaths;
                } else {
                    $this->model->{$this->field} = $this->s3Path;
                }
                $this->model->save();
            }
            
            Storage::disk('local')->delete($this->filePath);
        }
    }
}

