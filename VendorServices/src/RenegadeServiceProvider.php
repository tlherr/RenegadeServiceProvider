<?php

namespace Renegade\VendorServices;

use Renegade\VendorServices\Vendors\iRep;
use Silex\Application;
use Silex\ServiceProviderInterface;

//($twig, Filesystem $filesystem, Client $phantomJS, \TCPDF $tcpdf, $config, $directory)

class RenegadeServiceProvider implements ServiceProviderInterface {
    public function register(Application $app) {
        $app['renegade'] = array();
        $app['renegade']['irep'] = $app->share(function($app) {
            return new iRep($app['console'], $app['filesystem'], $app['twig'], $app['phantomjs'], $app['tcpdf'], $app['config'], $app['directory']);
        });
    }

    public function boot(Application $app) {}
}
