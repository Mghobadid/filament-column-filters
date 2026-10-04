<?php

// Testbench-only browser fixture; never used by consuming applications.
require __DIR__ . '/../../vendor/autoload.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~^/livewire-[a-z0-9]+/livewire(?:\.min)?\.js$~', $path)) {
    header('Content-Type: application/javascript');
    readfile(__DIR__ . '/../../vendor/livewire/livewire/dist/livewire.min.js');
    return;
}

$test = new class('browser') extends \Zvizvi\FilamentColumnFilters\Tests\TestCase
{
    public function browser(): void {}

    public function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('b', 32)));
    }

    public function start(): \Illuminate\Foundation\Application
    {
        $this->setUp();

        return $this->app;
    }
};
$app = $test->start();
$public = $app->publicPath();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($public . $path)) {
    return false;
}

\Livewire\Livewire::component('hidden-browser', \Zvizvi\FilamentColumnFilters\Tests\Fixtures\LayoutDonorsTable::class);
\Livewire\Livewire::component('hidden-relationship-browser', \Zvizvi\FilamentColumnFilters\Tests\Fixtures\HiddenRelationshipDonorsTable::class);
foreach (['Alpha', 'Beta', 'Zebra one', 'Zebra two', 'Zebra three', 'Excluded'] as $name) {
    \Zvizvi\FilamentColumnFilters\Tests\Fixtures\Category::create(['name' => $name]);
}
\Zvizvi\FilamentColumnFilters\Tests\Fixtures\Donor::create(['name' => 'Alice', 'status' => 'open', 'amount' => 1000]);
\Zvizvi\FilamentColumnFilters\Tests\Fixtures\Donor::create(['name' => 'Bob', 'status' => 'closed', 'amount' => 2000]);
\Illuminate\Support\Facades\Route::get('/', fn () => \Illuminate\Support\Facades\Blade::render(<<<'BLADE'
    <!doctype html><html><head>@livewireStyles @filamentStyles
    <link rel="stylesheet" href="/css/filament/filament/app.css">
    </head><body><livewire:hidden-browser :deferred="!request()->boolean('live')" />
    @filamentScripts @livewireScripts</body></html>
    BLADE));
\Illuminate\Support\Facades\Route::get('/relationship', fn () => \Illuminate\Support\Facades\Blade::render(<<<'BLADE'
    <!doctype html><html><head>@livewireStyles @filamentStyles
    <link rel="stylesheet" href="/css/filament/filament/app.css">
    </head><body><livewire:hidden-relationship-browser />
    @filamentScripts @livewireScripts</body></html>
    BLADE));
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request = \Illuminate\Http\Request::capture());
$response->send();
$kernel->terminate($request, $response);
