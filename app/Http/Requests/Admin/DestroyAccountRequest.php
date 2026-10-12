<?php

namespace App\Http\Requests\Admin;

use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Deleting an account takes all of its data with it, so the name has to be
 * typed over first.
 */
class DestroyAccountRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Account $account */
        $account = $this->route('account');

        return [
            'confirm_name' => ['required', 'string', Rule::in([$account->name])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_name.in' => __('Type the account name exactly to confirm.'),
        ];
    }
}
