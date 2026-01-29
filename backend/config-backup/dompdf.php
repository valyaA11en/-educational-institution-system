<?php

return [
    'show_warnings' => false,
    'public_path' => null,
    'chroot' => realpath(base_path()),
    'enable_font_subsetting' => false,
    'pdf_backend' => 'CPDF',
    'default_media_type' => 'screen',
    'default_paper_size' => 'a4',
    'default_font' => 'DejaVu Sans',
    'dpi' => 96,
    'enable_php' => false,
    'enable_javascript' => true,
    'enable_remote' => true,
    'font_dir' => storage_path('fonts'),
    'font_cache' => storage_path('fonts'),
    'temp_dir' => sys_get_temp_dir(),
    'log_output_file' => storage_path('logs/dompdf.log'),
];


