<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Project ID
    |--------------------------------------------------------------------------
    | Lihat di Firebase Console -> Project Settings -> General,
    | atau field "project_id" di file service account JSON.
    */
    'project_id' => env('FIREBASE_PROJECT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Path file service account (relatif terhadap base_path())
    |--------------------------------------------------------------------------
    */
    'credentials' => env('FIREBASE_CREDENTIALS', 'storage/app/firebase/service-account.json'),

];