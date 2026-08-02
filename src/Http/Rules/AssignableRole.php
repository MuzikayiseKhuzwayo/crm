<?php

namespace VentureDrake\LaravelCrm\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use VentureDrake\LaravelCrm\Models\Role;

/**
 * Reject role ids the caller is not entitled to hand out.
 *
 * Delegates to Role::assignableBy() so the validation rule, the role dropdown
 * and the assignment sites all share one predicate (crm_role = 1, the current
 * team when teams are enabled, and Owner only for an existing Owner). Without
 * this a tampered form payload could assign a role belonging to another tenant
 * or escalate the target to Owner.
 *
 * Running the Owner check here rather than only at the assignment site matters:
 * validation happens before the user row is written, so a rejected payload
 * leaves no half-created user behind.
 */
class AssignableRole implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! Role::assignableBy()->whereKey($value)->exists()) {
            $fail(ucfirst(__('laravel-crm::lang.user_invitation_role_invalid')));
        }
    }
}
