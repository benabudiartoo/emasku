<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Owner-only access. Someone else's record answers 404 so its existence never leaks.
 */
abstract class OwnerPolicy
{
    protected function owns(User $user, Model $model): Response
    {
        return $user->id === $model->getAttribute('user_id')
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function view(User $user, Model $model): Response
    {
        return $this->owns($user, $model);
    }

    public function update(User $user, Model $model): Response
    {
        return $this->owns($user, $model);
    }

    public function delete(User $user, Model $model): Response
    {
        return $this->owns($user, $model);
    }
}
