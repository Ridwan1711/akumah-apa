<?php

return [
    'defaults' => [
        'principal_name' => env('ROLE_CERTIFICATE_PRINCIPAL_NAME', "H. Cecep 'Ilman Fahmi, SH."),
        'principal_title' => env('ROLE_CERTIFICATE_PRINCIPAL_TITLE', 'Pimpinan Pondok Pesantren'),
        'stamp_path' => env('ROLE_CERTIFICATE_STAMP_PATH'),
    ],
];
