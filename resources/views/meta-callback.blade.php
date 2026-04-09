<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Conexao Meta | {{ config('app.name') }}</title>
    <style>
      :root {
        color-scheme: light;
        --bg: #f4f7fb;
        --text: #1b2745;
        --muted: #60779d;
        --line: #d7e1f0;
        --success: #0b9d67;
        --error: #db4b4b;
        --panel: rgba(255, 255, 255, 0.97);
      }

      * { box-sizing: border-box; }

      body {
        margin: 0;
        min-height: 100vh;
        display: grid;
        place-items: center;
        padding: 24px;
        background:
          radial-gradient(circle at top left, rgba(81, 69, 245, 0.10), transparent 28%),
          linear-gradient(180deg, #f9fbff 0%, var(--bg) 100%);
        font-family: "Segoe UI", Arial, sans-serif;
        color: var(--text);
      }

      .card {
        width: min(100%, 640px);
        background: var(--panel);
        border: 1px solid var(--line);
        border-radius: 28px;
        padding: 32px;
        box-shadow: 0 18px 40px rgba(30, 55, 90, 0.10);
      }

      .badge {
        display: inline-flex;
        padding: 10px 14px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #fff;
        background: {{ $result['status'] === 'success' ? 'var(--success)' : 'var(--error)' }};
      }

      h1 {
        margin: 18px 0 12px;
        font-size: 38px;
        line-height: 1.05;
      }

      p {
        margin: 0;
        color: var(--muted);
        font-size: 18px;
        line-height: 1.7;
      }

      .actions {
        margin-top: 28px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
      }

      .button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 12px 18px;
        border-radius: 14px;
        border: 1px solid var(--line);
        text-decoration: none;
        color: var(--text);
        font-weight: 600;
        background: #fff;
        cursor: pointer;
      }
    </style>
  </head>
  <body>
    <main class="card">
      <span class="badge">{{ $result['status'] === 'success' ? 'Meta conectado' : 'Conexao incompleta' }}</span>
      <h1>{{ $result['status'] === 'success' ? 'Retorno recebido com sucesso' : 'Nao foi possivel concluir a autorizacao' }}</h1>
      <p>{{ $result['message'] }}</p>

      <div class="actions">
        <button class="button" type="button" onclick="window.close()">Fechar esta janela</button>
        <a class="button" href="{{ url('/') }}">Voltar ao WooPack</a>
      </div>
    </main>

    <script>
      (() => {
        const payload = @json($result);

        if (window.opener && !window.opener.closed) {
          window.opener.postMessage({
            type: 'woopack-meta-oauth',
            payload,
          }, '{{ url('/') }}');
        }
      })();
    </script>
  </body>
</html>
