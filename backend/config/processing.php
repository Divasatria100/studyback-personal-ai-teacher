<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PDF Text Extraction
    |--------------------------------------------------------------------------
    |
    | spatie/pdf-to-text shells out to poppler's pdftotext binary. It is
    | provisioned in the Docker image; a custom path can be supplied for
    | non-standard environments (e.g. local Windows dev):
    |
    |   PDFTOTEXT_BIN=C:\path\to\pdftotext.exe
    |
    */

    'pdftotext_binary' => env('PDFTOTEXT_BIN', ''),

];
