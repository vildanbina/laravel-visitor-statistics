<?php

namespace vildanbina\Laravel\VisitorTracker\Test;

use vildanbina\Laravel\VisitorTracker\VisitorTrackerServiceProvider;
use vildanbina\Laravel\VisitorTracker\Tracker;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    /**
     * Load package service provider
     * @param  \Illuminate\Foundation\Application $app
     * @return vildanbina\Laravel\VisitorTracker\VisitorTrackerServiceProvider
     */
    protected function getPackageProviders($app)
    {
        return [VisitorTrackerServiceProvider::class];
    }

    /**
     * Load package alias
     * @param  \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'VisitorTracker' => Tracker::class,
        ];
    }
}
