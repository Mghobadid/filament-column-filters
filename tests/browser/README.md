Browser regression checks use the real package and Filament assets in a Testbench
fixture, with no application or vendor view overrides.

Install Composer and npm dependencies, then publish Testbench assets:

```sh
php vendor/bin/testbench filament:assets
php -S 127.0.0.1:8765 -t vendor/orchestra/testbench-core/laravel/public tests/browser/server.php
```

In another terminal, with Chrome installed:

```sh
node tests/browser/hidden-layout.mjs
```

`TEST_URL` overrides the fixture URL. `BROWSER_CHANNEL` overrides the Playwright
browser channel. The checks exercise popup triggers, deferred Apply, live
filtering, indicators, reset, and scoped relationship search with limited results.
