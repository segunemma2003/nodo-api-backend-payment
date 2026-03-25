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

    protected $tempS3Path;
    protected $finalS3Path;
    protected $model;
    protected $field;
    protected $category;

    public function __construct($tempS3Path, $finalS3Path, $model = null, $field = null, $category = null)
    {
        $this->tempS3Path = $tempS3Path;
        $this->finalS3Path = $finalS3Path;
        $this->model = $model;
        $this->field = $field;
        $this->category = $category;
    }

    public function handle(): void
    {
        $s3 = Storage::disk('s3');

        if (!$s3->exists($this->tempS3Path)) {
            return;
        }

        // Move from temp to final path within S3
        $s3->move($this->tempS3Path, $this->finalS3Path);

        if ($this->model && $this->field) {
            $this->model->refresh();
            $currentValue = $this->model->{$this->field};

            if ($this->category !== null) {
                $structured = is_array($currentValue) ? $currentValue : [];
                $structured[$this->category] = $this->finalS3Path;
                $this->model->{$this->field} = $structured;
            } elseif (is_array($currentValue)) {
                $updatedPaths = [];
                foreach ($currentValue as $path) {
                    if ($path === $this->tempS3Path) {
                        $updatedPaths[] = $this->finalS3Path;
                    } else {
                        $updatedPaths[] = $path;
                    }
                }
                $this->model->{$this->field} = $updatedPaths;
            } else {
                $this->model->{$this->field} = $this->finalS3Path;
            }
            $this->model->save();
        }
    }
}

