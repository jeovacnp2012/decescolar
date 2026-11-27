<?php

return [
    // API de integração de vacinação do UYSI Service (…/api/v1/integration). Token "uysi_…" gerado no UYSI.
    'url' => rtrim((string) env('CHAMADOS_API_URL', ''), '/'),
    'token' => env('CHAMADOS_API_TOKEN'),
    'timeout' => (int) env('CHAMADOS_API_TIMEOUT', 15),
    'verify_ssl' => (bool) env('CHAMADOS_API_VERIFY_SSL', true),
    'units_cache_minutes' => (int) env('CHAMADOS_UNITS_CACHE_MINUTES', 10),
];
