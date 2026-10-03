<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

/*
 * Demo mode only (APP_DEMO=true): one-click login as a seeded demo account,
 * so a visitor from the portfolio can try each role without typing a password.
 * ?to=/admin/... deep-links into a page; anything else falls back to the dashboard.
 */
Route::get('/demo/{account}', function (string $account) {
    abort_unless(config('app.demo'), 404);

    Auth::login(User::query()->where('email', "{$account}@demo.test")->firstOrFail());
    request()->session()->regenerate();

    $to = (string) request('to');

    return redirect(str_starts_with($to, '/admin') ? $to : '/admin');
})->whereIn('account', User::DEMO_LOGINS)->name('demo.login');
