<?php

namespace Send4\InfobipMail;

use GuzzleHttp\Client as HttpClient;
use Illuminate\Mail\TransportManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Send4\InfobipMail\Transport\InfobipTransport;

class InfobipServiceProvider extends ServiceProvider
{
    /**
     * Register the transport manager instance.
     *
     * @return void
     */
    public function register()
    {
        $this->app->afterResolving(TransportManager::class, function(TransportManager $manager) {
            $this->createInfobipDriver($manager);
        });
    }

    /**
     * Create an instance of the Infobip Swift Transport driver.
     *
     * @param \Illuminate\Mail\TransportManager $manager
     * @return \Send4\InfobipMail\Transport\InfobipTransport
     */
    protected function createInfobipDriver(TransportManager $manager)
    {
        $manager->extend('infobip', function() {
            $config = $this->app['config']->get('services.infobip', []);

            return new InfobipTransport(
                $this->guzzle($config),
                $config['api_key'],
                $config['base_url']
            );
        });
    }

    /**
     * Get a fresh Guzzle HTTP client instance.
     *
     * @param  array  $config
     * @return \GuzzleHttp\Client
     */
    protected function guzzle($config)
    {
        return new HttpClient(Arr::add(
            $config['guzzle'] ?? [], 'connect_timeout', 60
        ));
    }

}