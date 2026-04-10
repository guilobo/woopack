<?php

return [
    'company_name' => env('APP_COMPANY_NAME', 'Indoor Tech'),
    'support_email' => env('APP_SUPPORT_EMAIL', 'contato@indoortech.com.br'),
    'meta_app_id' => env('META_APP_ID'),
    'meta_app_secret' => env('META_APP_SECRET'),
    'meta_graph_version' => env('META_GRAPH_VERSION', 'v25.0'),
    'meta_wa_config_id' => env('META_WA_CONFIG_ID'),
    'whatsapp_webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
    'whatsapp_webhook_forward_url' => env('WHATSAPP_WEBHOOK_FORWARD_URL'),
    'whatsapp_webhook_forward_timeout' => (int) env('WHATSAPP_WEBHOOK_FORWARD_TIMEOUT', 5),
    'whatsapp_test_message_text' => env(
        'WHATSAPP_TEST_MESSAGE_TEXT',
        'Mensagem de teste do WooPack. Sua integracao com o WhatsApp Cloud API esta funcionando.'
    ),
];
