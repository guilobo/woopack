# WooPack

WooPack e uma ferramenta interna de operacao logistica para lojas WooCommerce. Ela centraliza a consulta de pedidos, mostra indicadores da operacao em tempo real e oferece um modo de embalagem pensado para acelerar a separacao e a conferencia dos itens antes do envio.

Esta versao foi migrada de um backend em `Node/Express` para `Laravel 13 + MySQL`, preservando o frontend React e a experiencia visual do sistema original.

## O que a ferramenta faz

O WooPack foi construido para apoiar a rotina de expedicao de pedidos. Na pratica, ele permite:

- autenticar o acesso ao sistema com uma senha interna simples;
- consultar pedidos do WooCommerce em tempo real;
- visualizar metricas operacionais no dashboard;
- filtrar pedidos por status;
- buscar pedidos por numero ou cliente;
- abrir um modo de embalagem com foco em conferencia;
- marcar localmente quais pedidos ja foram embalados;
- manter o status de embalagem no banco local sem alterar o WooCommerce.

## Fluxo de uso

1. O operador entra no sistema com a senha configurada no `.env`.
2. O dashboard mostra volume de pedidos, vendas e distribuicao por status.
3. A tela de pedidos permite localizar rapidamente o pedido certo.
4. O modo embalagem organiza os pedidos pendentes em fila.
5. Ao finalizar a conferencia, o pedido e marcado como embalado no banco local.

## Arquitetura atual

- Backend: Laravel 13
- Frontend: React + Vite
- Banco local: MySQL do XAMPP
- Origem dos pedidos: API do WooCommerce
- Persistencia local: tabela `packing_statuses`
- Autenticacao: sessao Laravel com senha unica

## Principais funcionalidades

### Dashboard operacional

Exibe:

- total de pedidos consultados;
- total vendido;
- contagem por status;
- vendas agrupadas por dia.

### Lista de pedidos

Permite:

- filtrar por `processing`, `on-hold`, `completed` e `any`;
- pesquisar por ID ou nome do cliente;
- visualizar rapidamente status, total e cliente;
- marcar ou desmarcar o status de embalagem.

### Modo embalagem

Foi mantido como o coracao operacional da ferramenta:

- mostra apenas os pedidos pendentes de embalagem;
- permite navegar pela fila;
- destaca itens, quantidades, SKU, endereco e notas do cliente;
- remove o pedido da fila quando a embalagem e concluida.

## Estrutura do repositorio

- `woopack/`: aplicacao principal em Laravel
- `woopack/app/`: controllers, services, middleware e models
- `woopack/resources/js/`: frontend React migrado
- `woopack/database/`: migrations e banco local
- `woopack/tests/`: testes automatizados da API
- `woopack_legacy/`: copia local do projeto antigo, mantida fora do commit

## Configuracao local

O projeto foi preparado para rodar no XAMPP com VirtualHost.

Dominio local configurado:

- `http://indoor.woopack`

Variaveis principais no arquivo `woopack/.env`:

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

## Como rodar

Dentro da pasta `woopack/`:

```bash
composer install
npm install
php artisan migrate --force
npm run dev
```

Para ambiente servido pelo Apache/XAMPP:

```bash
npm run build
php artisan migrate --force
```

Depois disso, acesse `http://indoor.woopack`.

## Endpoints mantidos da versao anterior

- `POST /api/login`
- `POST /api/logout`
- `GET /api/auth/check`
- `GET /api/orders`
- `GET /api/orders/{id}`
- `POST /api/orders/{id}/pack`
- `GET /api/stats`

## Validacao realizada

Na migracao atual, foram executados com sucesso:

```bash
php artisan test
npm run lint
npm run build
```

## Resumo

O WooPack agora tem a mesma proposta operacional do sistema anterior, mas com uma base mais organizada para evolucao: Laravel no backend, MySQL para persistencia local, React preservado no frontend e integracao direta com WooCommerce para manter os dados sempre atualizados.
