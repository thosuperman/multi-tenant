<?php

/*
 * This file is part of the hyn/multi-tenant package.
 *
 * (c) Daniël Klabbers <daniel@klabbers.email>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see https://laravel-tenancy.com
 * @see https://github.com/hyn/multi-tenant
 */

namespace Hyn\Tenancy\Providers\Tenants;

use Hyn\Tenancy\Contracts\Repositories\WebsiteRepository;
use Hyn\Tenancy\Environment;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Arr;

class QueueProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->extend('queue', function (QueueManager $queue) {
            $queue->createPayloadUsing(function (string $connection, string $queue = null, array $payload = []) {
                if (isset($payload['website_id'])) {
                    return [];
                }

                /** @var Environment $environment */
                $environment = resolve(Environment::class);
                $tenant = $environment->tenant();

                return $tenant ? [
                    'website_id' => $tenant->id,
                ] : [];
            });

            $queue->before(function (JobProcessing $event) {
                /** @var array $payload */
                $payload = $event->job->payload();
                if ($command = Arr::get($payload, 'data.command')) {
                    $command = unserialize($command);
                }

                $key = $command->website_id ?? $payload['website_id'] ?? null;

                if ($key) {
                    /** @var Environment $environment */
                    $environment = resolve(Environment::class);
                    /** @var WebsiteRepository $repository */
                    $repository = resolve(WebsiteRepository::class);

                    $tenant = $repository->findById($key);

                    $environment->tenant($tenant);
                }
            });

            return $queue;
        });
    }
}
