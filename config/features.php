<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Target Kinerja Tahunan RENJA
    |--------------------------------------------------------------------------
    |
    | Sakelar rollback aman. Jika dinonaktifkan, RENJA kembali memakai alur UI
    | lama tanpa menghapus data target tahunan yang sudah tersimpan.
    |
    */
    'renja_annual_targets' => env('FEATURE_RENJA_ANNUAL_TARGETS', false),
];
