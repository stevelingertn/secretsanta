<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Category CSV path
    |--------------------------------------------------------------------------
    |
    | Source file for CategorySeeder. Defaults to reference/carClasses.csv.
    | Override with CARSHOW_CATEGORIES_CSV, --path on app:seed-categories,
    | or CategorySeeder::$path in tests.
    */
    'categories_csv' => env('CARSHOW_CATEGORIES_CSV'),
];
