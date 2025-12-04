<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Redis;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (env('REDIS_CLIENT') === 'predis' && env('REDIS_URL')) {
            $redisUrl = env('REDIS_URL');
            if (str_starts_with($redisUrl, 'rediss://')) {
                $this->app['config']->set('database.redis.default.parameters.scheme', 'tls');
                $this->app['config']->set('database.redis.default.parameters.ssl', [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ]);
                
                $this->app['config']->set('database.redis.cache.parameters.scheme', 'tls');
                $this->app['config']->set('database.redis.cache.parameters.ssl', [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ]);
            }
        }
    }
}
