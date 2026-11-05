<?php

return [
    // Token que o chamados-multi-tenant usa para chamar a API DESTE sistema (POST /api/v1/atestados/{protocolo}/pronto).
    'api_token' => env('DECESCOLAR_API_TOKEN'),
    // Por decisão do contrato, quem envia o e-mail ao responsável é o chamados. Ative só se isso mudar.
    'send_email' => (bool) env('DECESCOLAR_SEND_EMAIL', false),
    'attach_pdf_in_email' => (bool) env('DECESCOLAR_ATTACH_PDF', true),
];
