<?php
declare(strict_types=1);

return [
    'APP_NAME' => 'TRAXTER CRM',
    'APP_URL' => 'https://seu-dominio.com',
    'APP_BASE_PATH' => '',
    'APP_DEBUG' => false,
    'APP_TIMEZONE' => 'America/Sao_Paulo',
    'APP_KEY' => 'defina-uma-chave-unica-aqui',
    'APPROVAL_REQUIRE_HTTPS' => true,
    'SERVICE_ORDER_APPROVAL_TTL_HOURS' => 72,
    'MAIL_FROM_EMAIL' => '',
    'MAIL_FROM_NAME' => 'TRAXTER CRM',
    'DB_HOST' => 'localhost',
    'DB_PORT' => 3306,
    'DB_NAME' => 'nome_do_banco',
    'DB_USER' => 'usuario_do_banco',
    'DB_PASS' => 'senha_do_banco',
    'DB_CHARSET' => 'utf8mb4',
    'DB_REQUIRE_SYNC_BEFORE_RUN' => true,
    'DB_RESET_ENABLED' => false,
    'DB_RESET_TARGET' => 'production',
    'DB_RESET_ALLOWED_HOST' => '',
    'DB_RESET_ALLOWED_DB' => '',
    'DB_RESET_CONFIRM_PHRASE' => 'RESETAR-BANCO-PRODUCAO',
    'DB_RESET_PRESERVE_TABLES' => '',
    'DB_RESET_SEED_MINIMUM' => true,
];
