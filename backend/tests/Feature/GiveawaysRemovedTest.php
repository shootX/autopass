<?php

// Owner, 2026-11-06: giveaways are gone with their tables. A paid ticket for a prize reads as a lottery.

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

it('has no giveaway tables, routes or shop fields', function () {
    expect(Schema::hasTable('tickets'))->toBeFalse()
        ->and(Schema::hasTable('user_tickets'))->toBeFalse();

    $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri());
    expect($uris->filter(fn (string $uri) => str_contains($uri, 'tickets'))->values()->all())->toBe([]);

    $user = makeUser('555 74 06 01');
    $this->withToken(cwAs($user))->getJson('/api/shop/home')->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonMissingPath('tickets')
        ->assertJsonMissingPath('my_tickets');
});
