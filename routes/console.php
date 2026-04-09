<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('woopack:create-admin {name} {email} {password}', function () {
    $user = User::query()->updateOrCreate(
        ['email' => $this->argument('email')],
        [
            'name' => $this->argument('name'),
            'password' => Hash::make($this->argument('password')),
            'is_admin' => true,
        ],
    );

    $this->info("Administrador pronto: {$user->email}");
})->purpose('Create or update the initial WooPack administrator');
