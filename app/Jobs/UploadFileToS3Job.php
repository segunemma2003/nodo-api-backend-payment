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
    protected $category;

    public function __construct($filePath, $s3Path, $model = null, $field = null, $category = null)
    {
        $this->filePath = $filePath;
        $this->s3Path = $s3Path;
        $this->model = $model;
        $this->field = $field;
        $this->category = $category;
    }

    public function handle(): void
    {
        if (Storage::disk('local')->exists($this->filePath)) {
            $fileContent = Storage::disk('local')->get($this->filePath);
            Storage::disk('s3')->put($this->s3Path, $fileContent);

            if ($this->model && $this->field) {
                $this->model->refresh();
                $currentValue = $this->model->{$this->field};

                if ($this->category !== null) {
                    // Structured format: { "cac_document": "path", ... }
                    $structured = is_array($currentValue) ? $currentValue : [];
                    $structured[$this->category] = $this->s3Path;
                    $this->model->{$this->field} = $structured;
                } elseif (is_array($currentValue)) {
                    // Legacy flat array format
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

