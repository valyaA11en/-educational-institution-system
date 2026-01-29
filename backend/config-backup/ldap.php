<?php

return [
    'enabled' => env('LDAP_ENABLED', false),
    'default' => [
        'host' => env('LDAP_HOST', 'ldap.example.com'),
        'port' => env('LDAP_PORT', 389),
        'base_dn' => env('LDAP_BASE_DN', 'dc=example,dc=com'),
        'bind_dn' => env('LDAP_BIND_DN'),
        'bind_password' => env('LDAP_BIND_PASSWORD'),
        'user_filter' => env('LDAP_USER_FILTER', '(uid=%s)'),
        'attribute_mapping' => [
            'email' => env('LDAP_EMAIL_ATTR', 'mail'),
            'fio' => env('LDAP_NAME_ATTR', 'cn'),
        ],
    ],
];


