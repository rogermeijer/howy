<?php

namespace App\Http\Requests\Admin;

use App\Models\AiUsageRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiUsageIndexRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'account' => ['nullable', 'integer'],
            'step' => ['nullable', 'string', 'max:40'],
            'model' => ['nullable', 'string', 'max:80'],
            'agent' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in([AiUsageRecord::SUCCEEDED, AiUsageRecord::FAILED])],
            'subject_type' => ['nullable', 'string', 'max:40'],
            'subject_id' => ['nullable', 'integer'],
            'thread' => ['nullable', 'string', 'max:255'],
            'batch' => ['nullable', 'boolean'],
        ];
    }
}
