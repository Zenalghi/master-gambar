<?php

namespace App\Providers;

use App\Models\Transaksi;
use App\Policies\TransaksiPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Daftarkan Policy Anda di sini
        Transaksi::class => TransaksiPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Baris ini akan secara otomatis mendaftarkan semua policy di atas
        $this->registerPolicies();
    }
}
