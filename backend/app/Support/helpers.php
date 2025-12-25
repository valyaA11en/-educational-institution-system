<?php

if (!function_exists('domain_path')) {
    /**
     * Get the path to a domain directory.
     */
    function domain_path(string $domain, string $path = ''): string
    {
        return app_path('Domains/'.$domain.($path ? '/'.$path : ''));
    }
}

