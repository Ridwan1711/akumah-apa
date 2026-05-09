<?php

return [
    'min_installment_amount' => (int) env('PAYMENT_MIN_INSTALLMENT_AMOUNT', 10000),
    'gateway_pending_ttl_minutes' => (int) env('PAYMENT_GATEWAY_PENDING_TTL_MINUTES', 60),
];
