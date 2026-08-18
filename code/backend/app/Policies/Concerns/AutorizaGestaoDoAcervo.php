<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AutorizaGestaoDoAcervo
{
    public function viewAny(User $user): bool
    {
        return $user->podeGerirAcervo();
    }

    public function view(User $user, mixed $model): bool
    {
        return $user->podeGerirAcervo();
    }

    public function create(User $user): bool
    {
        return $user->podeGerirAcervo();
    }

    public function update(User $user, mixed $model): bool
    {
        return $user->podeGerirAcervo();
    }

    public function delete(User $user, mixed $model): bool
    {
        return $user->podeGerirAcervo();
    }
}
