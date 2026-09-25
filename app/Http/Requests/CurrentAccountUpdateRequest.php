<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CurrentAccountUpdateRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            // Membership is enforced by the database rather than by a hand-written
            // check, and it leaks nothing: an account the user is not in is
            // indistinguishable from one that does not exist.
            'account_id' => [
                'required',
                'integer',
                Rule::exists('account_user', 'account_id')->where('user_id', $this->user()->id),
            ],
        ];
    }
}
