<?php

namespace App\Http\Requests\Colleagues;

use App\Concerns\ColleagueValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateColleagueRequest extends FormRequest
{
    use ColleagueValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->colleagueRules();
    }
}
