<?php

namespace VentureDrake\LaravelCrm\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use VentureDrake\LaravelCrm\Models\Role;

/**
 * Reject role ids the caller is not entitled to hand out.
 *
 * Delegates to Role::assignable() so the validation rule, the role dropdown and
 * the assignment sites all share one predicate (crm_role = 1, plus the current
 * team when teams are enabled). Without this a tampered form payload could
 * assign a role belonging to another tenant.
 */
class AssignableRole implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! Role::assignable()->whereKey($value)->exists()) {
            $fail(ucfirst(__('laravel-crm::lang.user_invitation_role_invalid')));
        }
    }
}
