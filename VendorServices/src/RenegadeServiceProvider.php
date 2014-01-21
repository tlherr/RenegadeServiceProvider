<?php

namespace Renegade\VendorServices;

use Renegade\VendorServices\Vendors\iRep;
use Silex\Application;
use Silex\ServiceProviderInterface;

class RenegadeServiceProvider implements ServiceProviderInterface {
    public function register(Application $app) {
        $app['renegade_irep'] =  new iRep($app['console'], $app['twig'],  $app['filesystem'], $app['phantomjs'], $app['tcpdf'], $app['config'], $app['directory']);
        return $app;
    }

    public function boot(Application $app) {}
}
