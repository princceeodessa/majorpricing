<?php

return [
    'token_header' => env('INTEGRATION_TOKEN_HEADER', 'X-Integration-Key'),

    'tokens' => [
        'erp' => env('INTEGRATION_ERP_TOKEN', 'majpr-erp-change-me'),
        'payments' => env('INTEGRATION_PAYMENTS_TOKEN', 'majpr-payments-change-me'),
    ],

    'erp' => [
        'orders_push_url' => env('ERP_ORDERS_PUSH_URL'),
        'outgoing_token' => env('ERP_OUTGOING_TOKEN'),
        'outgoing_token_header' => env('ERP_OUTGOING_TOKEN_HEADER', env('INTEGRATION_TOKEN_HEADER', 'X-Integration-Key')),
        'timeout' => (int) env('ERP_HTTP_TIMEOUT', 10),
        'connect_timeout' => (int) env('ERP_HTTP_CONNECT_TIMEOUT', 5),
    ],
];
