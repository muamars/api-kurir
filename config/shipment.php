<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Batas Takeover per Pengiriman
    |--------------------------------------------------------------------------
    |
    | Jumlah maksimum admin boleh men-takeover (menarik kembali ke pending)
    | sebuah pengiriman yang sudah di-assign ke kurir. Setelah batas tercapai,
    | takeover berikutnya ditolak dan pengiriman ditandai `needs_review = true`
    | untuk dieskalasi ke supervisor (tidak didaur ulang otomatis).
    |
    | Atur lewat env SHIPMENT_MAX_TAKEOVER.
    |
    */
    'max_takeover' => (int) env('SHIPMENT_MAX_TAKEOVER', 3),

];
