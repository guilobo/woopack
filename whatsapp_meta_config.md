# Configuracao Meta + WhatsApp Business Platform para Sistemas Web

## Objetivo

Este documento descreve, de forma tecnica e reaproveitavel, como configurar a integracao entre um sistema web e a **Meta / WhatsApp Business Platform (Cloud API)** usando **Embedded Signup** para que cada usuario conecte a propria conta do WhatsApp Business dentro do sistema.

O foco aqui e registrar o que de fato funcionou em uma implementacao real, incluindo:

- configuracoes necessarias no app da Meta
- arquitetura recomendada no backend e frontend
- fluxo de autenticacao que funcionou com mais estabilidade
- erros comuns encontrados
- tecnicas de diagnostico
- boas praticas para futuras integracoes

Este arquivo **nao deve conter segredos, tokens, IDs reais, dominios reais ou dados pessoais**. Sempre use placeholders.

---

## Visao geral da arquitetura

Para um sistema multiusuario, a arquitetura recomendada e:

1. Cada usuario do sistema conecta o proprio WhatsApp Business.
2. O sistema possui **um app da Meta** proprio.
3. O sistema inicia o **Embedded Signup** da Meta.
4. A Meta retorna um **authorization code** para o sistema.
5. O backend troca esse `code` por um `access_token`.
6. O backend descobre automaticamente os ativos do WhatsApp vinculados a esse token:
   - `waba_id`
   - `phone_number_id`
   - `display_phone_number`
7. O sistema salva a conexao por usuario.
8. O frontend oferece acoes como:
   - conectar
   - testar conexao
   - desconectar

### Ponto critico aprendido

Nao dependa da interface da Meta devolver no frontend todos os dados tecnicos do WhatsApp. Em varios cenarios, o callback volta **apenas com `code` e `state`**.

Por isso, o backend deve ser capaz de:

- trocar o `authorization code` por token
- consultar a Graph API
- descobrir sozinho `waba_id` e `phone_number_id`

Essa foi a chave para estabilizar a integracao.

---

## Pre-requisitos

Antes de comecar, voce precisa ter:

- uma conta de desenvolvedor Meta
- um app criado no painel da Meta
- URLs publicas HTTPS para:
  - politica de privacidade
  - termos de servico
  - exclusao de dados do usuario
- um backend capaz de receber callback OAuth
- um frontend web que possa abrir popup e receber retorno do fluxo

---

## Estrutura recomendada no sistema

### Backend

O backend deve ser o responsavel por:

- gerar a URL do fluxo Meta
- gerar e validar `state`
- receber o callback OAuth
- trocar `authorization code` por `access_token`
- opcionalmente trocar por token de maior duracao
- descobrir ativos do WhatsApp pela Graph API
- salvar conexao por usuario
- testar e remover conexao
- registrar logs detalhados

### Frontend

O frontend deve:

- abrir o popup do Embedded Signup
- monitorar o retorno da Meta
- aguardar a finalizacao automatica
- nunca depender que o usuario preencha manualmente IDs tecnicos

### Persistencia sugerida

Uma tabela por usuario, por exemplo:

- `user_id`
- `meta_access_token` (criptografado)
- `meta_token_type`
- `meta_token_expires_at` nullable
- `meta_business_id` nullable
- `meta_waba_id`
- `meta_phone_number_id`
- `meta_display_phone_number`
- `meta_verified_name`
- `meta_quality_rating`
- timestamps

Observacao: `business_id` pode ficar vazio em alguns fluxos. O importante para operacao costuma ser `waba_id` e `phone_number_id`.

---

## O que configurar no app da Meta

## 1. Criar o app

No painel da Meta Developers:

1. Crie um novo app.
2. Escolha o caso de uso relacionado a WhatsApp Business.
3. Adicione o produto ou caso de uso de **Facebook Login for Business** se o fluxo exigir OAuth web.
4. Habilite o fluxo de **Embedded Signup** para WhatsApp.

---

## 2. Configuracoes basicas do app

Em **Configuracoes do app > Basico**:

- preencha nome do app
- email de contato
- categoria
- URL da politica de privacidade
- URL dos termos de servico
- URL de exclusao de dados do usuario

### Campo importante: App Domains

No campo **Dominios do aplicativo**:

- informe apenas o dominio do sistema, sem protocolo

Exemplo:

```text
app.exemplo.com
```

Nao use:

```text
https://app.exemplo.com
```

Se houver duplicidade, remova. Deixe apenas o dominio final correto.

---

## 3. Configuracoes do Facebook Login for Business

Em **Login do Facebook para Empresas > Configuracoes**:

Habilite:

- `Client OAuth Login`
- `Web OAuth Login`
- `Embedded Browser OAuth Login`
- `Use Strict Mode for Redirect URIs`
- `Login with the JavaScript SDK`
- `Force HTTPS`

### Valid OAuth Redirect URIs

Cadastre a URL exata do callback do seu sistema.

Exemplo:

```text
https://app.exemplo.com/auth/meta/callback
```

Esse valor precisa ser **identico** ao `redirect_uri` usado pelo backend ao iniciar o OAuth.

Esse foi um dos pontos mais sensiveis da integracao.

### Allowed Domains for the JavaScript SDK

No campo **Dominios permitidos para o SDK do JavaScript**, use o dominio completo com HTTPS.

Exemplo:

```text
https://app.exemplo.com
```

---

## 4. Embedded Signup / configuracao do WhatsApp

No fluxo do WhatsApp Embedded Signup, o app precisa ter um `config_id`.

Esse `config_id` sera usado pelo sistema para abrir o fluxo hospedado pela Meta.

Em implementacoes futuras, o backend deve possuir configuracoes como:

- `META_APP_ID`
- `META_APP_SECRET`
- `META_GRAPH_VERSION`
- `META_WA_CONFIG_ID`
- `META_REDIRECT_URI`

Todos esses valores devem ficar no backend, nunca hardcoded no frontend.

---

## Escopos que funcionaram

Os escopos que funcionaram no fluxo foram:

```text
whatsapp_business_management
whatsapp_business_messaging
```

### Escopo que causou problema

O escopo abaixo causou erro de permissao invalida no popup:

```text
business_management
```

Conclusao pratica:

- para o fluxo de WhatsApp Cloud API via Embedded Signup, use somente os escopos realmente necessarios
- nao adicione escopos extras sem necessidade

---

## Fluxo tecnico recomendado

## 1. Backend gera a URL OAuth

Em vez de depender exclusivamente de `FB.login`, a abordagem mais estavel foi o backend gerar uma URL OAuth completa, por exemplo:

```text
https://www.facebook.com/{graph_version}/dialog/oauth
```

Com parametros equivalentes a:

- `client_id`
- `redirect_uri`
- `state`
- `scope=whatsapp_business_management,whatsapp_business_messaging`
- `response_type=code`
- `override_default_response_type=true`
- `config_id`
- `extras`

### Extras recomendados

O objeto `extras` pode incluir:

```json
{
  "sessionInfoVersion": "3",
  "version": "v4",
  "featureType": "whatsapp_business_app_onboarding"
}
```

### Por que isso foi melhor

Quando o backend controla a URL OAuth:

- o `redirect_uri` fica deterministico
- fica mais facil depurar
- reduz conflitos entre frontend, SDK e callback
- evita inconsistencias entre o popup e a troca do `code`

---

## 2. Gerar e validar `state`

Cada tentativa de conexao deve gerar um `state` unico, associado ao usuario autenticado.

Boas praticas:

- gerar `state` aleatorio
- salvar em sessao ou storage temporario no backend
- validar no callback
- descartar apos uso

---

## 3. Abrir popup no frontend

O frontend deve:

1. chamar um endpoint do backend para receber a configuracao do Embedded Signup
2. abrir a `auth_url`
3. monitorar o fechamento do popup
4. monitorar o callback confirmado no backend

Recomendacao:

- usar polling de um endpoint como `/api/meta/connect/status`
- opcionalmente escutar `postMessage`
- considerar a conexao concluida apenas quando o backend confirmar sucesso

---

## 4. Callback OAuth

Crie uma rota publica de callback, por exemplo:

```text
GET /auth/meta/callback
```

Ela deve:

- validar `state`
- capturar `code`
- registrar logs
- devolver uma pequena pagina HTML que:
  - envia `postMessage` para a janela principal
  - tenta fechar o popup

### Licao importante

Nao presuma que a Meta vai devolver no callback:

- `business_id`
- `waba_id`
- `phone_number_id`

Em testes reais, o retorno veio varias vezes **somente com `code` e `state`**.

Por isso, o callback precisa salvar o que chegou, mas o backend deve completar os dados depois.

---

## 5. Troca do `authorization code` por token

Ao receber o `code`, o backend deve chamar a Graph API para trocar por token.

### Erro critico encontrado

O erro mais recorrente foi:

```text
Error validating verification code. Please make sure your redirect_uri is identical to the one you used in the OAuth dialog request
```

### O que resolveu

1. Garantir que o `redirect_uri` usado na troca do `code` seja exatamente o mesmo usado na abertura do popup.
2. Garantir que esse mesmo `redirect_uri` esteja em:
   - `Valid OAuth Redirect URIs`
3. Manter o fluxo controlado pelo backend.

### Compatibilidade adicional

Em alguns cenarios, pode ser util tentar um fallback de `redirect_uri` compativel com:

```text
https://www.facebook.com/connect/login_success.html
```

Mas isso deve ser tratado como compatibilidade, nao como fluxo principal.

---

## 6. Trocar por token de maior duracao

Depois da troca do `code`, recomenda-se tentar obter um token de maior duracao.

Vantagens:

- menos reconexoes
- menos falhas de expiracao imediata
- operacao mais estavel

Sempre registre logs do resultado dessa troca.

---

## 7. Descobrir `waba_id` e `phone_number_id` automaticamente

Essa foi a tecnica mais importante aprendida no processo.

### Problema

Mesmo com callback funcionando, a Meta nem sempre retorna os IDs do WhatsApp no frontend.

### Solucao que funcionou

Depois de obter o token do usuario:

1. Chame `debug_token`.
2. Leia `granular_scopes`.
3. Localize o escopo `whatsapp_business_management`.
4. Extraia os `target_ids` desse escopo.
5. Trate esses `target_ids` como candidatos a `waba_id`.
6. Para cada `waba_id`, consulte:

```text
/{waba_id}/phone_numbers
```

7. Pegue o primeiro numero valido retornado e salve:

- `waba_id`
- `phone_number_id`
- `display_phone_number`
- `verified_name`
- `quality_rating`

### Resultado pratico

Com isso, a conexao pode ser finalizada mesmo quando o frontend nao recebe os IDs tecnicos no retorno do popup.

---

## Fluxo recomendado no frontend

## Durante a conexao

O frontend pode mostrar estados como:

- aguardando popup
- aguardando callback
- codigo recebido da Meta
- finalizando conexao
- conectado com sucesso
- erro de conexao

## Depois da conexao

Recomendacoes:

- limpar o campo de `authorization code`
- mostrar apenas:
  - numero conectado
  - token mascarado
  - WABA ID
  - Phone Number ID
  - status da conexao
  - botao de testar
  - botao de desconectar

### O campo `Authorization code`

E normal esse campo ficar vazio depois da conexao.

Motivo:

- o `authorization code` e temporario
- ele deve ser usado uma vez para troca por token
- depois disso, o sistema nao deve mante-lo visivel

---

## Endpoint de teste da conexao

Tenha um endpoint como:

```text
POST /api/whatsapp/test
```

Ele deve validar:

- existe token salvo
- existe `waba_id`
- existe `phone_number_id`
- a Graph API responde com sucesso para o numero configurado

Esse teste e essencial para diferenciar:

- conexao salva parcialmente
- conexao valida de fato

---

## Endpoint de desconexao

Tenha um endpoint como:

```text
DELETE /api/whatsapp/connection
```

Ele deve:

- remover ou invalidar dados locais da conexao
- limpar campos tecnicos
- nao depender do frontend limpar manualmente estados internos

---

## Logs recomendados

Crie logs especificos para essa integracao.

### Eventos uteis

- `meta.oauth.callback`
- `whatsapp.connect.attempt`
- `whatsapp.connect.failed`
- `whatsapp.connect.success`
- `whatsapp.disconnect`
- `whatsapp.test.success`
- `whatsapp.test.failed`

### O que registrar

Registre apenas informacoes tecnicas seguras, por exemplo:

- usuario interno
- query keys recebidas no callback
- presenca ou ausencia de `code`
- presenca ou ausencia de `waba_id`
- status HTTP das chamadas para Meta
- mensagens de erro da Graph API

### O que nao registrar

Nao registre em texto puro:

- `app_secret`
- access tokens completos
- authorization codes completos
- dados sensiveis do cliente

No maximo, use mascaramento.

---

## Erros comuns e como corrigir

## 1. Invalid Scopes

Erro tipico:

```text
Invalid Scopes: business_management
```

### Correcao

Use somente:

- `whatsapp_business_management`
- `whatsapp_business_messaging`

---

## 2. Domain not included in app domains

Erro tipico:

```text
The domain of this URL isn't included in the app's domains
```

### Correcao

Em `Configuracoes do app > Basico`:

- adicione o dominio em `Dominios do aplicativo`

Exemplo:

```text
app.exemplo.com
```

Em `Login do Facebook para Empresas > Configuracoes`:

- adicione o dominio em `Dominios permitidos para o SDK do JavaScript`

Exemplo:

```text
https://app.exemplo.com
```

---

## 3. Redirect URI mismatch

Erro tipico:

```text
Error validating verification code. Please make sure your redirect_uri is identical to the one you used in the OAuth dialog request
```

### Correcao

- use um unico `redirect_uri`
- monte o popup no backend
- use exatamente o mesmo valor na troca do `code`
- confirme que a URL esta cadastrada em `Valid OAuth Redirect URIs`

---

## 4. Callback retorna so `code` e `state`

### Sintoma

O frontend fica parado em algo como:

```text
Codigo recebido da Meta. Finalizando conexao...
```

### Causa

O sistema esperava `waba_id` e `phone_number_id` no retorno do popup.

### Correcao

Descobrir os IDs no backend usando o token e a Graph API.

---

## 5. Conexao salva sem `phone_number_id`

### Sintoma

O sistema mostra conectado, mas o teste falha.

### Correcao

Nao considere a conexao valida ate existir:

- token
- `waba_id`
- `phone_number_id`

---

## O que nao fazer

- nao dependa so do `postMessage` da Meta para obter todos os dados
- nao dependa que o frontend monte a logica sensivel de OAuth sozinho
- nao salve conexao incompleta como se estivesse valida
- nao exiba tokens completos no frontend
- nao mantenha `authorization code` armazenado apos o uso
- nao documente segredos reais em arquivos de projeto

---

## Checklist de configuracao da Meta

Antes de testar o sistema, confirme:

- app criado na Meta
- politica de privacidade publicada
- termos de servico publicados
- exclusao de dados publicada
- categoria do app preenchida
- dominio do app configurado
- callback OAuth configurado
- dominio permitido para JS SDK configurado
- `config_id` do Embedded Signup configurado no backend
- `META_APP_ID` configurado
- `META_APP_SECRET` configurado
- `META_GRAPH_VERSION` configurado
- escopos corretos usados no popup
- callback do sistema acessivel por HTTPS

---

## Checklist de implementacao no sistema

- gerar `state` no backend
- abrir popup com URL montada no backend
- receber callback OAuth
- trocar `code` por token
- tentar token de maior duracao
- descobrir `waba_id` e `phone_number_id` pela Graph API
- salvar conexao por usuario
- testar a conexao
- permitir desconexao
- registrar logs detalhados

---

## Fluxo resumido recomendado

```text
Frontend clica em "Conectar WhatsApp Business"
-> Backend devolve auth_url + state
-> Frontend abre popup
-> Meta autentica usuario
-> Meta redireciona para /auth/meta/callback
-> Callback salva code/state e fecha popup
-> Frontend detecta callback concluido
-> Backend troca code por token
-> Backend descobre waba_id e phone_number_id
-> Backend salva conexao
-> Frontend atualiza status para conectado
-> Usuario pode testar a conexao
```

---

## Recomendacao final para futuros projetos

Se o objetivo for construir uma integracao mais previsivel e facil de reproduzir em outros sistemas:

- trate o Embedded Signup como um fluxo **backend-first**
- deixe o frontend apenas iniciar e refletir o estado
- suponha que a Meta pode mudar pequenos detalhes do retorno do popup
- implemente fallback e descoberta automatica de ativos
- registre logs desde o inicio da implementacao

Essa combinacao reduz bastante o tempo perdido com erros de `redirect_uri`, callbacks incompletos e conexoes salvas parcialmente.
