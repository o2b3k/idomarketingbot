<?php

use App\Models\User;
use DefStudio\Telegraph\Models\TelegraphBot;
use Filament\Panel;
use Laravel\Horizon\Horizon;
use Propaganistas\LaravelPhone\PhoneNumber;

/*
 * Infrastructure smoke tests.
 *
 * Note: phpunit.xml pins the test environment to sqlite :memory:, array cache,
 * and sync queue, so the live MySQL/Redis/Horizon drivers cannot be asserted
 * here — those are verified manually (php artisan db:show, redis-cli ping).
 * These tests confirm the new stack is installed and the Filament panel works.
 */

it('serves the health check endpoint', function () {
    $this->get('/up')->assertOk();
});

it('has the required infrastructure packages installed', function () {
    expect(class_exists(Horizon::class))->toBeTrue()
        ->and(class_exists(TelegraphBot::class))->toBeTrue()
        ->and(class_exists(Panel::class))->toBeTrue()
        ->and(class_exists(PhoneNumber::class))->toBeTrue();
});

it('renders the Filament admin login page', function () {
    $this->get('/admin/login')->assertOk();
});

it('redirects guests away from the admin panel', function () {
    $this->get('/admin')->assertStatus(302);
});

it('allows an authenticated user to reach the admin panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertOk();
});
