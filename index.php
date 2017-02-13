<?php

define('INDEX_PHP_PASSTHROUGH', true);

require_once '../vendor/colinmollenhour/credis/Client.php';

$redisClient = new Credis_Client();
$processId = getmypid();
$requestUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

class CachedProcInfo {
    public $pid = null;
    public $running = false;
    public $requestUri = null;
    public $requestMethod = null;
    public $startedAt = null;
    public $endedAt = null;
    public $clientInfo = null;
    
    public function getRunTime(): float {
        $now = microtime(true);
        $then = $this->startedAt;
        
        if ($this->endedAt) {
            $now = $this->endedAt;
        }
        
        return round(($now - $then), 5);
    }
    
    public function getLastSeen(): string {
        $lastSeen = $this->startedAt;
        
        if ($this->endedAt) {
            $lastSeen = $this->endedAt;
        }
        
        return date('H:i:s', $lastSeen);
    }
    
    public function getStatus(): string {
        if (!file_exists("/proc/{$this->pid}")) {
            if ($this->endedAt) {
                $val = "✓ ☠ Process finished (last completed in {$this->getRunTime()} sec)";
            } else {
                $val = "☠ Process is dead (killed)";
            }
            
            return $val;
        }
        
        if (!$this->running) {
            if ($this->endedAt) {
                return "✓ Idle. Completed in {$this->getRunTime()} sec";    
            } else {
                return "☠ Completed (killed / unknown end time)";
            }
        }

        return "👣 Running for {$this->getRunTime()} sec";
    }
    
    public function render(): void {
        echo PHP_EOL;
        echo "PID:\t\t{$this->pid}" . PHP_EOL;
        echo "Status:\t\t{$this->getStatus()}" . PHP_EOL;
        echo "URL:\t\t{$this->requestUri}" . PHP_EOL;
        echo "Method:\t\t{$this->requestMethod}" . PHP_EOL;
        echo "Client:\t\t{$this->clientInfo}" . PHP_EOL;
        echo "Last seen:\t{$this->getLastSeen()}" . PHP_EOL;
    }
};

$cacheKey = "fpm_proc_{$processId}";
$cacheCurrent = $redisClient->get($cacheKey);

if ($cacheCurrent) {
    $cacheCurrent = unserialize($cacheCurrent);
}

if (!($cacheCurrent instanceof CachedProcInfo)) {
    $cacheCurrent = new CachedProcInfo();
}

$cacheCurrent->pid = $processId;
$cacheCurrent->requestUri = $requestUri;
$cacheCurrent->startedAt = microtime(true);
$cacheCurrent->endedAt = null;
$cacheCurrent->running = true;
$cacheCurrent->requestMethod = $_SERVER['REQUEST_METHOD'];
$cacheCurrent->clientInfo = $_SERVER['REMOTE_ADDR'] . " ({$_SERVER['HTTP_USER_AGENT']})";

$redisClient->set($cacheKey, serialize($cacheCurrent));
$redisClient->expire($cacheKey, 900); // keep running proc info for up to 15 minutes

register_shutdown_function(function () use ($redisClient, $cacheCurrent, $cacheKey) {
    $cacheCurrent->endedAt = microtime(true);
    $cacheCurrent->running = false;

    $redisClient->set($cacheKey, serialize($cacheCurrent));
    $redisClient->expire($cacheKey, 120); // keep complete proc info for 2 minutes
});

if ($_SERVER['REQUEST_URI'] === '/passthrough-status') {
    // Debug mode: List FPM processes
    $procKeys = $redisClient->keys('fpm_proc_*');
    
    $procObjs = [];
    $procObjsRunning = [];
    $procObjsStopped = [];
    
    foreach ($procKeys as $procKey) {
        $processInfo = $redisClient->get($procKey);
        $processInfo = unserialize($processInfo);
        
        if (!$processInfo instanceof CachedProcInfo) {
            continue;
        }
        
        $procObjs[] = $processInfo;
        
        if ($processInfo->running) {
            $procObjsRunning[] = $processInfo;    
        } else {
            $procObjsStopped[] = $processInfo;
        }
    }
    
    $procCountTotal = count($procObjs);
    $procCountActive = count($procObjsRunning);
    $procCountStopped = count($procObjsStopped);
    
    // Output
    header('Content-Type: text/plain; charset=utf8');
    
    echo 'Passthrough php-fpm process info' . PHP_EOL . PHP_EOL;
    echo " [{$procCountTotal}]\tTotal process count" . PHP_EOL;
    echo " [{$procCountActive}]\tRunning process count" . PHP_EOL;
    echo " [{$procCountStopped}]\tCompleted process count" . PHP_EOL;
    
    echo PHP_EOL;
    echo '----- Active processes -----' . PHP_EOL;
    
    foreach ($procObjsRunning as $processInfo) {
       $processInfo->render();
    }

    echo PHP_EOL;
    echo '----- Completed processes -----' . PHP_EOL;

    foreach ($procObjsStopped as $processInfo) {
        $processInfo->render();
    }
    
} else {
    require_once "index.passthrough.php";    
}
