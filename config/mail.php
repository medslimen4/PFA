<?php

return [

    'driver' => env('MAIL_DRIVER', 'smtp'),

    'host' => env('MAIL_HOST', 'mail.privateemail.com'),

    'port' => env('MAIL_PORT', 465),
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@pepinieremahdia.me'),
        'name' => env('MAIL_FROM_NAME', 'Laravel'),
    ],

    'encryption' => env('MAIL_ENCRYPTION', 'ssl'),

    'username' => env('MAIL_USERNAME', 'noreply@pepinieremahdia.me'),

    'password' => env('MAIL_PASSWORD', 'salimaazerty0123'),
    'auth' => env('MAIL_AUTH', 'LOGIN'),

    'sendmail' => '/usr/sbin/sendmail -bs',

    'markdown' => [
        'theme' => 'default',
        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

    'log_channel' => env('MAIL_LOG_CHANNEL'),

];

