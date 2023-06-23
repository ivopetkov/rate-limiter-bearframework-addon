<?php

/*
 * Rate limiter for Bear Framework
 * https://github.com/ivopetkov/rate-limiter-bearframework-addon
 * Copyright (c) Ivo Petkov
 * Free to use under the MIT license.
 */

namespace IvoPetkov\BearFrameworkAddons;

use BearFramework\App;

/**
 *
 */
class RateLimiter
{

    /**
     * Format: function(string $key, string $limit, $data){}
     * 
     * @var null|callable 
     */
    private $logger = null;

    /**
     * 
     * @var string|null
     */
    public $ip = null;

    /**
     * Set a function to be called when the limit is reached and the next log attempts will fail the specified limit.
     * It there is a limit of 5/m, the logger will be called on the 4th log attempt that will also return TRUE. The next one will return FALSE and the logger will not be called.
     * 
     * @param callable $logger Format: function(string $key, string $limit, $data){}
     * @return self
     */
    public function setLogger(callable $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Sets the IP to be used when logIP() is called
     * 
     * @param string $ip
     * @return self
     */
    public function setIP(string $ip): self
    {
        $this->ip = $ip;
        return $this;
    }

    /**
     * Logs an action and checks if the rate limits specified are reached.
     * 
     * @param string $key The action key.
     * @param array $limits The limiting rates. Format: ['10/s', '10/m', '10/h', '10/d'] for second, minute, hour and day.
     * @param mixed $loggerData
     * @return boolean Returns FALSE if one of the limits is reached.
     */
    public function log(string $key, array $limits, $loggerData = null): bool
    {
        $currentTime = time();
        $keyHash = base_convert(substr(md5($key), 0, 10), 16, 32);
        $minItemsTime = $currentTime - 86400; // items older than 1 day are automatically removed 
        $filename = $this->getDataFileName();
        if (is_file($filename)) {
            $data = include $filename;
        } else {
            $data = [];
        }
        $hasChange = false;
        $newData = [];
        foreach ($data as $dataKey => $dataTimes) {
            $newTimes = [];
            foreach ($dataTimes as $dataTime) {
                if ($dataTime >= $minItemsTime) {
                    $newTimes[] = $dataTime;
                } else {
                    $hasChange = true;
                }
            }
            if (!empty($newTimes)) {
                $newData[$dataKey] = $newTimes;
            } else {
                $hasChange = true;
            }
        }
        $data = $newData;
        unset($newData);

        $addKey = true;
        if (isset($data[$keyHash])) {
            foreach ($limits as $limit) {
                $limitParts = explode('/', $limit);
                if (isset($limitParts[0], $limitParts[1]) && is_numeric($limitParts[0])) {
                    $limitValue = (int)$limitParts[0];
                    $limitType = $limitParts[1];
                    if ($limitType === 's') {
                        $minLimitTime = $currentTime - 1;
                    } elseif ($limitType === 'm') {
                        $minLimitTime = $currentTime - 60;
                    } elseif ($limitType === 'h') {
                        $minLimitTime = $currentTime - 3600;
                    } elseif ($limitType === 'd') {
                        $minLimitTime = $minItemsTime;
                    } else {
                        throw new \Exception('Invalid limit format (' . $limit . ')!');
                    }
                    $matchedTimes = array_filter($data[$keyHash], function ($time) use ($minLimitTime) {
                        return $time >= $minLimitTime;
                    });
                    $matchedTimesCount = sizeof($matchedTimes);
                    if ($matchedTimesCount + 1 === $limitValue) {
                        if (is_callable($this->logger)) {
                            call_user_func($this->logger, $key, $limit, $loggerData);
                        }
                    }
                    if ($matchedTimesCount >= $limitValue) {
                        $addKey = false;
                        break;
                    }
                }
            }
        } else {
            $data[$keyHash] = [];
        }
        if ($addKey) {
            $data[$keyHash][] = $currentTime;
            $hasChange = true;
        }
        if ($hasChange) {
            file_put_contents($filename, '<?php ' . "\n" . 'return ' . var_export($data, true) . ';');
            $this->clearFileNameCache($filename);
        }
        return $addKey;
    }

    /**
     * Logs current visitor IP and checks if the rate limits specified are reached.
     * 
     * @param array $limits The limiting rates. Format: ['10/s', '10/m', '10/h', '10/d'] for second, minute, hour and day.
     * @param mixed $loggerData
     * @return boolean Returns FALSE if one of the limits is reached.
     */
    public function logIP(array $limits, $loggerData = null): bool
    {
        if ($this->ip === null) {
            if (!isset($_SERVER['REMOTE_ADDR'])) {
                throw new \Exception('Cannot find ip');
            }
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = $this->ip;
        }
        return $this->log('ip-' . $ip, $limits, $loggerData);
    }

    /**
     * Removes all logged data.
     *
     * @return self
     */
    public function reset(): self
    {
        $filename = $this->getDataFileName();
        if (is_file($filename)) {
            unlink($filename);
            $this->clearFileNameCache($filename);
        }
        return $this;
    }

    /**
     * 
     * @return string
     */
    private function getDataFileName(): string
    {
        return sys_get_temp_dir() . '/ivopetkov-rate-limiter.php';
    }

    /**
     * 
     * @param string $filename
     * @return void
     */
    private function clearFileNameCache(string $filename): void
    {
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($filename);
        }
    }
}
