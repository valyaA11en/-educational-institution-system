<?php

require_once __DIR__.'/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Container\Container;

echo "=== Finding env issue ===\n";

// Hook into container to catch the 'env' call
Container::macro('debugMake', function ($abstract, array $parameters = []) {
    if ($abstract === 'env') {
        echo "FOUND IT! Someone is trying to make 'env' class\n";
        
        // Get stack trace to see where it comes from
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 10);
        foreach ($trace as $i => $frame) {
            if (isset($frame['file']) && isset($frame['line'])) {
                echo "#{$i} {$frame['file']}:{$frame['line']}\n";
            }
        }
        
        throw new Exception("Found the env call!");
    }
    return $this->make($abstract, $parameters);
});

try {
    echo "1. Creating Application...\n";
    $app = Application::configure(basePath: __DIR__)
        ->create();
    
    echo "2. Created successfully\n";
    
    // Replace make method to intercept 'env' calls
    $app->instance('originalMake', $app);
    
    echo "3. Getting Console Kernel...\n";
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    echo "4. Kernel created\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "=== Debug Complete ===\n";