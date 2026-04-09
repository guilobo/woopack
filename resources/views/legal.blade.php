<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title }} | {{ config('app.name') }}</title>
    <style>
      :root {
        color-scheme: light;
        --bg: #f4f7fb;
        --panel: rgba(255, 255, 255, 0.96);
        --text: #1b2745;
        --muted: #60779d;
        --line: #d7e1f0;
        --primary: #5145f5;
        --primary-soft: rgba(81, 69, 245, 0.12);
      }

      * { box-sizing: border-box; }

      body {
        margin: 0;
        font-family: "Segoe UI", Arial, sans-serif;
        background:
          radial-gradient(circle at top left, rgba(81, 69, 245, 0.10), transparent 30%),
          linear-gradient(180deg, #f9fbff 0%, var(--bg) 100%);
        color: var(--text);
      }

      .shell {
        max-width: 960px;
        margin: 0 auto;
        padding: 48px 20px 72px;
      }

      .brand {
        display: inline-flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 28px;
        text-decoration: none;
        color: inherit;
      }

      .brand-badge {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        background: linear-gradient(135deg, #6458ff, #4a3ef0);
        color: #fff;
        display: grid;
        place-items: center;
        font-size: 26px;
        box-shadow: 0 18px 34px rgba(81, 69, 245, 0.24);
      }

      .brand-copy strong {
        display: block;
        font-size: 30px;
        line-height: 1;
      }

      .brand-copy span {
        display: block;
        margin-top: 6px;
        color: #7f94b9;
        letter-spacing: 0.12em;
        font-size: 13px;
        text-transform: uppercase;
      }

      .panel {
        background: var(--panel);
        border: 1px solid var(--line);
        border-radius: 28px;
        padding: 32px;
        box-shadow: 0 18px 40px rgba(30, 55, 90, 0.08);
      }

      .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 999px;
        background: var(--primary-soft);
        color: var(--primary);
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
      }

      h1 {
        margin: 20px 0 12px;
        font-size: clamp(34px, 6vw, 54px);
        line-height: 1.02;
      }

      .intro {
        margin: 0 0 28px;
        color: var(--muted);
        font-size: 20px;
        line-height: 1.6;
      }

      .section + .section {
        margin-top: 26px;
        padding-top: 26px;
        border-top: 1px solid var(--line);
      }

      h2 {
        margin: 0 0 10px;
        font-size: 22px;
      }

      p, li {
        color: var(--muted);
        font-size: 17px;
        line-height: 1.75;
      }

      ul {
        margin: 0;
        padding-left: 20px;
      }

      .callout {
        margin-top: 28px;
        padding: 20px 22px;
        border-radius: 20px;
        background: #f8fbff;
        border: 1px solid var(--line);
      }

      .callout strong {
        display: block;
        margin-bottom: 8px;
        font-size: 18px;
        color: var(--text);
      }

      .nav {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 28px;
      }

      .nav a {
        padding: 12px 16px;
        border-radius: 14px;
        background: #fff;
        border: 1px solid var(--line);
        color: var(--text);
        text-decoration: none;
        font-weight: 600;
      }

      .footer {
        margin-top: 22px;
        color: var(--muted);
        font-size: 14px;
      }

      @media (max-width: 640px) {
        .shell { padding: 28px 16px 44px; }
        .panel { padding: 24px; border-radius: 22px; }
        .intro { font-size: 18px; }
        p, li { font-size: 16px; }
      }
    </style>
  </head>
  <body>
    <main class="shell">
      <a class="brand" href="{{ url('/') }}">
        <span class="brand-badge">□</span>
        <span class="brand-copy">
          <strong>{{ config('app.name') }}</strong>
          <span>Logistica Inteligente</span>
        </span>
      </a>

      <article class="panel">
        <span class="eyebrow">{{ $eyebrow }}</span>
        <h1>{{ $title }}</h1>
        <p class="intro">{{ $intro }}</p>

        @foreach ($sections as $section)
          <section class="section">
            <h2>{{ $section['title'] }}</h2>

            @foreach ($section['paragraphs'] ?? [] as $paragraph)
              <p>{{ $paragraph }}</p>
            @endforeach

            @if (! empty($section['items']))
              <ul>
                @foreach ($section['items'] as $item)
                  <li>{{ $item }}</li>
                @endforeach
              </ul>
            @endif
          </section>
        @endforeach

        <div class="callout">
          <strong>{{ $calloutTitle }}</strong>
          <p>{{ $calloutBody }}</p>
          <p>Contato: <a href="mailto:{{ config('woopack.support_email') }}">{{ config('woopack.support_email') }}</a></p>
        </div>

        <nav class="nav">
          <a href="{{ route('legal.privacy') }}">Politica de Privacidade</a>
          <a href="{{ route('legal.terms') }}">Termos de Servico</a>
          <a href="{{ route('legal.data-deletion') }}">Exclusao de Dados</a>
        </nav>

        <p class="footer">Ultima atualizacao: {{ now()->format('d/m/Y') }} · {{ config('app.name') }} operado por {{ config('woopack.company_name') }}</p>
      </article>
    </main>
  </body>
</html>
