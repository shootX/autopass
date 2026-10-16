<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

it('has no duplicate route names', function () {
    $names = [];
    foreach (Route::getRoutes() as $route) {
        if ($name = $route->getName()) {
            $names[$name][] = $route->uri();
        }
    }
    $dupes = array_filter($names, fn ($uris) => count($uris) > 1);
    expect($dupes)->toBe([]);
});

it('resolves the admin search routes to their own urls', function () {
    expect(route('admin.search_clients', [], false))->toBe('/dashboard/search_clients')
        ->and(route('admin.search_user_cars', [], false))->toBe('/dashboard/search_user_cars')
        ->and(route('admin.search_wash_services', [], false))->toBe('/dashboard/search_wash_services')
        ->and(route('admin.search_car_brands', [], false))->toBe('/dashboard/search_car_brands')
        ->and(route('admin.search_car_models', [], false))->toBe('/dashboard/search_car_models');
});

it('can cache the routes', function () {
    try {
        expect(Artisan::call('route:cache'))->toBe(0);
    } finally {
        Artisan::call('route:clear');
    }
});
