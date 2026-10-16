<?php

namespace App\Rules;

use App\Support\EmailIdentity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueEmailIdentity implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = EmailIdentity::query((string) $value);

        if ($this->ignoreUserId !== null) {
            $query->whereKeyNot($this->ignoreUserId);
        }

        if ($query->exists()) {
            $fail('The :attribute has already been taken.');
        }
    }
}
