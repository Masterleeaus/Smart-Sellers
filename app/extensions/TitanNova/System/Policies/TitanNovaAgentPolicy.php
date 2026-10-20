<?php

namespace App\Extensions\TitanNova\System\Policies;

use App\Extensions\TitanNova\System\Models\TitanNovaAgent;
use App\Models\User;

class TitanNovaAgentPolicy
{
    /**
     * Determine whether the user can view any agents.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the agent.
     */
    public function view(User $user, TitanNovaAgent $agent): bool
    {
        return $user->id === $agent->user_id;
    }

    /**
     * Determine whether the user can create agents.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the agent.
     */
    public function update(User $user, TitanNovaAgent $agent): bool
    {
        return $user->id === $agent->user_id;
    }

    /**
     * Determine whether the user can delete the agent.
     */
    public function delete(User $user, TitanNovaAgent $agent): bool
    {
        return $user->id === $agent->user_id;
    }

    /**
     * Determine whether the user can restore the agent.
     */
    public function restore(User $user, TitanNovaAgent $agent): bool
    {
        return $user->id === $agent->user_id;
    }

    /**
     * Determine whether the user can permanently delete the agent.
     */
    public function forceDelete(User $user, TitanNovaAgent $agent): bool
    {
        return $user->id === $agent->user_id;
    }
}
