<?php

namespace Renegade\VendorServices;

use renegade\services\irep\iRep;
use Silex\Application;
use Silex\ServiceProviderInterface;

class RenegadeServiceProvider implements ServiceProviderInterface {
    public function register(Application $app) {
        $app['renegade'] = array();
        $app['renegade']['irep'] = $app->share(function ($app) {
            $app->flush();
            return new iRep();
        });
    }

    public function boot(Application $app) {}
}
