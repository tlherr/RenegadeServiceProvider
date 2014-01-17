<?php

namespace Renegade\VendorServices;

use Renegade\VendorServices\Vendors\iRep;
use Silex\Application;
use Silex\ServiceProviderInterface;

//(InputInterface $input, OutputInterface $output, $twig, Filesystem $filesystem, Client $phantomJS, \TCPDF $tcpdf, $config, $directory)

class RenegadeServiceProvider implements ServiceProviderInterface {
    public function register(Application $app) {
        return new iRep();
    }

    public function boot(Application $app) {}
}
