<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Barryvdh\DomPDF\Facade\Pdf;

class DomPdfServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Pdf::setOptions([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => true,
        ]);
    }
}


