<?php

/*
 * Rate limiter for Bear Framework
 * https://github.com/ivopetkov/rate-limiter-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

use BearFramework\App;

$app = App::get();
$context = $app->contexts->get(__DIR__);

$context->classes
    ->add('IvoPetkov\BearFrameworkAddons\RateLimiter', 'classes/RateLimiter.php');

$app->shortcuts
    ->add('rateLimiter', function () {
        return new IvoPetkov\BearFrameworkAddons\RateLimiter();
    });
