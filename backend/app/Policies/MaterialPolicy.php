<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\User;

/**
 * Ownership policy for materials and everything nested under them
 * (API Design §5, §17). Nested resources are always loaded through their
 * owning material, so this single check guards every material-rooted route.
 */
class MaterialPolicy
{
    /**
     * Determine whether the user can view a material (and anything nested).
     */
    public function view(User $user, Material $material): bool
    {
        return $user->id === $material->user_id;
    }
}
