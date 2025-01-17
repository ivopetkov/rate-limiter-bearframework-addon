<?php

/*
 * Rate limiter for Bear Framework
 * https://github.com/ivopetkov/rate-limiter-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

/**
 * @runTestsInSeparateProcesses
 */
class RateLimiterTest extends BearFramework\AddonTests\PHPUnitTestCase
{

    /**
     * 
     */
    public function testRateLimiter()
    {
        $app = $this->getApp();

        $loggerData = null;
        $app->rateLimiter->setLogger(function (string $action, string $key, string $limit) use (&$loggerData): void {
            $loggerData = [$action, $key, $limit];
        });

        $app->rateLimiter->reset();

        $action = 'action1';
        $key = 'test1';
        $limit1 = '3/m';
        $limit2 = '4/h';

        // OK
        $this->assertTrue($app->rateLimiter->log($action, $key, [$limit1, $limit2]));
        $this->assertNull($loggerData);

        // OK
        $this->assertTrue($app->rateLimiter->log($action, $key, [$limit1, $limit2]));
        $this->assertNull($loggerData);

        // OK + Logger for 3/m
        $this->assertTrue($app->rateLimiter->log($action, $key, [$limit1, $limit2]));
        $this->assertTrue($loggerData[0] === $action);
        $this->assertTrue($loggerData[1] === $key);
        $this->assertTrue($loggerData[2] === $limit1);
        $loggerData = null;

        // FAIL because of 3/m
        $this->assertFalse($app->rateLimiter->log($action, $key, [$limit1, $limit2]));
        $this->assertNull($loggerData);

        // Wait
        sleep(61);

        // OK + Logger for 4/h
        $this->assertTrue($app->rateLimiter->log($action, $key, [$limit1, $limit2]));
        $this->assertTrue($loggerData[0] === $action);
        $this->assertTrue($loggerData[1] === $key);
        $this->assertTrue($loggerData[2] === $limit2);
        $loggerData = null;

        // FAIL because of 4/h
        $this->assertFalse($app->rateLimiter->log($action, $key, [$limit1, $limit2]));
        $this->assertNull($loggerData);
    }
}
