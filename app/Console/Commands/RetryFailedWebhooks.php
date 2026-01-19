<?php

namespace App\Console\Commands;

use App\Services\WebhookService;
use Illuminate\Console\Command;

class RetryFailedWebhooks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhooks:retry-failed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed webhook deliveries every hour';

    protected WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        parent::__construct();
        $this->webhookService = $webhookService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Retrying failed webhooks...');
        $this->webhookService->retryFailedWebhooks();
        $this->info('Webhook retry completed.');
    }
}
