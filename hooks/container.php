<?php

use NetRivet\Container\Container;

add_action('pp_container_binding', function (Container $container) {
    $container
        ->when('ProPhoto\DesignerPlugin\Middleware\ApiMiddleware')
        ->needs('ProPhoto\Core\Service\Design\BundlerInterface')
        ->give('ProPhoto\S3DesignBundles\Design\S3Bundler');
});
