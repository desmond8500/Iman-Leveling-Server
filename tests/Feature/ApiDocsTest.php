<?php

use Illuminate\Support\Facades\Route;

it('sert la page de documentation Swagger', function () {
    $this->get('/docs')
        ->assertOk()
        ->assertSee('swagger-ui')
        ->assertSee('/openapi.json');
});

it('publie une description OpenAPI valide', function () {
    $spec = json_decode(file_get_contents(public_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($spec['openapi'])->toStartWith('3.')
        ->and($spec['paths'])->not->toBeEmpty();
});

it('documente chaque route de l\'API (et uniquement elles)', function () {
    $spec = json_decode(file_get_contents(public_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR);

    $documented = [];
    foreach ($spec['paths'] as $path => $operations) {
        foreach (array_keys($operations) as $method) {
            $documented[] = strtoupper($method).' api'.$path;
        }
    }

    $actual = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
        ->flatMap(fn ($route) => collect($route->methods())
            ->reject(fn ($method) => in_array($method, ['HEAD', 'OPTIONS']))
            ->map(fn ($method) => $method.' '.$route->uri()))
        ->values()
        ->all();

    expect($documented)->toEqualCanonicalizing($actual);
});
