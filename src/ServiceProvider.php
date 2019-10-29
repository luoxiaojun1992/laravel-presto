<?php

namespace Lxj\Laravel\Presto;

use Lxj\Laravel\Presto\Connectors\HttpConnector;
use Lxj\Laravel\Presto\Eloquent\Model;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        Model::setConnectionResolver($this->app['db']);
        Model::setEventDispatcher($this->app['events']);
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        // Add database driver.
        $this->app->resolving('db', function ($db) {
            $db->extend('presto', function ($config, $name) {
                $config['name'] = $name;
                $database = $config['catalog'] . '.' . $config['schema'];
                $prefix = $config['prefix'] ?? '';
                return new Connection(null, $database, $prefix, $config);
            });
        });
    }

}
