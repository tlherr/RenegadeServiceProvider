<?php
/**
 * Created by PhpStorm.
 * User: Tom
 * Date: 16/01/2014
 * Time: 15:22
 */

namespace renegade;


use renegade\services\iRep;
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
