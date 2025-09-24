<?php

namespace App\Policies;

use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransaksiPolicy
{
    /**
     * Izinkan admin melakukan apa saja.
     */
    public function before(User $user, string $ability): bool|null
    {
        // PERBAIKAN DI SINI: Akses nama role melalui relasi
        if ($user->role->name === 'admin') {
            return true;
        }
        return null;
    }

    /**
     * Tentukan apakah user bisa mengupdate transaksi.
     */
    public function update(User $user, Transaksi $transaksi): bool
    {
        // Izinkan jika user adalah pemilik transaksi
        return $user->id === $transaksi->user_id;
    }

    /**
     * Tentukan apakah user bisa menghapus transaksi.
     */
    public function delete(User $user, Transaksi $transaksi): bool
    {
        // Izinkan jika user adalah pemilik transaksi
        return $user->id === $transaksi->user_id;
    }
}
