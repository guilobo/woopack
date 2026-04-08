# WooPack App

Aplicacao principal do WooPack em `Laravel 13 + React + MySQL`.

Esta pasta contem a versao atual do sistema, migrada do backend antigo em Node para Laravel, mantendo a mesma proposta visual e operacional.

## Funcionalidades

- login com senha unica;
- dashboard com metricas de pedidos e vendas;
- listagem de pedidos com busca e filtros;
- modo embalagem com fila de pedidos pendentes;
- persistencia local do status de embalagem;
- leitura dos pedidos diretamente do WooCommerce.

## Stack

- Laravel 13
- React
- Vite
- Tailwind CSS
- MySQL

## Configuracao principal

Arquivo `.env`:

```env
APP_URL=http://indoor.woopack
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=woopack
DB_USERNAME=root
DB_PASSWORD=

WOOCOMMERCE_URL=https://sua-loja.com
WOOCOMMERCE_KEY=ck_...
WOOCOMMERCE_SECRET=cs_...
ADMIN_PASSWORD=sua_senha
```

## Comandos uteis

```bash
composer install
npm install
php artisan migrate --force
php artisan test
npm run lint
npm run build
```

## Endpoints

- `POST /api/login`
- `POST /api/logout`
- `GET /api/auth/check`
- `GET /api/orders`
- `GET /api/orders/{id}`
- `POST /api/orders/{id}/pack`
- `GET /api/stats`
