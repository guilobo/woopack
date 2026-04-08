<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="woopack-base-url" content="{{ url('/') }}" />
    <title>WooPack</title>
    @vite('resources/js/main.tsx')
  </head>
  <body>
    <div id="root"></div>
  </body>
</html>
