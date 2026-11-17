<?php

namespace App\Policies;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GenerationPolicy
{
    /**
     * Authenticated users may list generations (scoped to their own in the query).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Users may only view their own generations.
     *
     * Cross-user access returns 404 so another user's generation id is not confirmed.
     */
    public function view(User $user, Generation $generation): Response
    {
        return $this->owns($user, $generation)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Any authenticated user may create generations.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Generations are not user-editable after create.
     */
    public function update(User $user, Generation $generation): bool
    {
        return false;
    }

    /**
     * Users may only delete their own generations.
     */
    public function delete(User $user, Generation $generation): Response
    {
        return $this->owns($user, $generation)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    private function owns(User $user, Generation $generation): bool
    {
        return $user->id === $generation->user_id;
    }
}
