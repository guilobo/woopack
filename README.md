# WooPack

WooPack e uma ferramenta interna de operacao logistica para lojas WooCommerce. Ela centraliza a consulta de pedidos, mostra indicadores da operacao em tempo real e oferece um modo de embalagem pensado para acelerar a separacao e a conferencia dos itens antes do envio.

Esta versao roda em `Laravel 13 + MySQL`, preserva o frontend React do projeto original e agora funciona em modo multiusuario: cada conta tem a propria conexao WooCommerce e o proprio status local de embalagem.

## O que a ferramenta faz

O WooPack foi construido para apoiar a rotina de expedicao de pedidos. Na pratica, ele permite:

- autenticar o acesso ao sistema com uma senha interna simples;
- autenticar usuarios reais com sessao Laravel;
- cadastrar novos usuarios por convite;
- configurar uma conexao WooCommerce por conta;
- consultar pedidos do WooCommerce em tempo real;
- visualizar metricas operacionais no dashboard;
- filtrar pedidos por status;
- buscar pedidos por numero ou cliente;
- abrir um modo de embalagem com foco em conferencia;
- marcar localmente quais pedidos ja foram embalados;
- manter o status de embalagem no banco local sem alterar o WooCommerce.

## Fluxo de uso

1. Um administrador inicial cria ou atualiza a conta admin via comando Artisan.
2. O admin gera convites para novos usuarios.
3. O convidado aceita o convite, cria a propria senha e entra no sistema.
4. Cada usuario configura a propria loja WooCommerce.
5. O dashboard mostra volume de pedidos, vendas e distribuicao por status daquela conta.
6. O modo embalagem registra o status local apenas para o usuario logado.

## Arquitetura atual

- Backend: Laravel 13
- Frontend: React + Vite
- Banco local: MySQL do XAMPP
- Origem dos pedidos: API do WooCommerce
- Persistencia local: `packing_statuses`, `woo_commerce_connections` e `invitations`
- Autenticacao: usuarios reais com sessao Laravel

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

- `app/`: controllers, services, middleware e models
- `resources/js/`: frontend React migrado
- `database/`: migrations e banco local
- `tests/`: testes principais em Pest
- `woopack_legacy/`: copia local do projeto antigo, mantida fora do commit

## Configuracao local

O projeto foi preparado para rodar no XAMPP com VirtualHost.

Dominio local configurado:

- `http://indoor.woopack`

Variaveis principais no arquivo `.env`:

```env
APP_URL=http://indoor.woopack

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=woopack
DB_USERNAME=root
DB_PASSWORD=
```

As credenciais do WooCommerce nao ficam mais no `.env`: cada usuario salva sua propria conexao dentro do sistema.

## Como rodar

Na raiz do projeto:

```bash
composer install
npm install
php artisan migrate --force
php artisan woopack:create-admin "Admin WooPack" admin@example.com secret-pass
npm run dev
```

Para ambiente servido pelo Apache/XAMPP:

```bash
npm run build
php artisan migrate --force
```

Depois disso, acesse `http://indoor.woopack`.

## Deploy DreamHost

O projeto inclui uma automacao local para deploy em DreamHost baseada em Git.

Arquivos envolvidos:

- `.serverconfig`: credenciais e caminhos do servidor
- `.serverconfig.example`: modelo sem segredos
- `scripts/deploy-dreamhost.ps1`: script de deploy

Configuracao opcional:

- `deploy_php_bin`: caminho do binario PHP usado nos comandos `artisan` em producao

Fluxo do deploy:

1. executa testes locais;
2. gera o build de producao local;
3. gera um `git bundle` com a branch publicada;
4. envia esse bundle para um repositorio bare no servidor;
5. faz checkout remoto da aplicacao;
6. envia `vendor/`, `public/build/` e o `.env` de producao;
7. sincroniza `public/` com o webroot do dominio;
8. roda migrations e caches do Laravel em producao.

Para publicar:

```powershell
pwsh -File .\scripts\deploy-dreamhost.ps1
```

O `.env` remoto e montado automaticamente usando:

- credenciais do MySQL da `.serverconfig`;
- as demais configuracoes gerais do `.env` local.

Depois do primeiro deploy, crie o admin inicial no servidor:

```bash
php artisan woopack:create-admin "Admin WooPack" admin@example.com secret-pass
```

## Endpoints principais

- `POST /api/login`
- `POST /api/logout`
- `GET /api/auth/check`
- `GET /api/me`
- `GET /api/integration`
- `PUT /api/integration`
- `POST /api/invitations`
- `GET /api/invitations/{token}`
- `POST /api/invitations/accept`
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

O WooPack agora preserva a proposta operacional do sistema anterior, mas com uma base mais robusta para crescer: contas reais no Laravel, conexao WooCommerce por usuario, status de embalagem isolado por conta, convites para cadastro e testes principais em Pest antes de cada entrega.
