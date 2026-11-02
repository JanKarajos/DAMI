<?php
// Skopírujte ako config.local.php a upravte podľa potreby.
return [
    'base_url' => 'https://rezervacie.ozdami.sk',
    'mail' => [
        'transport'  => 'smtp',
        'from_email' => 'rezervacie@ozdami.sk',
        'admin_copy' => 'oz.dami9@gmail.com',
        'smtp' => [
            'host'       => 'smtp.m1.websupport.sk',
            'port'       => 465,
            'encryption' => 'ssl',
            'username'   => 'rezervacie@ozdami.sk',
            'password'   => 'TAJNE-HESLO',
        ],
    ],
];
