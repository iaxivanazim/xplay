<?php
namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PipeSeparatedNumbers implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $values = explode('|', $value);

        if (empty(array_filter($values))) {
            $fail("The :attribute must contain at least one value.");
            return;
        }

        foreach ($values as $v) {
            if (!is_numeric(trim($v))) {
                $fail("The :attribute must contain only numbers separated by '|'. Invalid value: '{$v}'.");
                return;
            }
            if ((float) trim($v) <= 0) {
                $fail("All values in :attribute must be greater than 0. Invalid value: '{$v}'.");
                return;
            }
        }
    }
}