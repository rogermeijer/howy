<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class GoLiveRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'colleagues' => ['present', 'array', 'max:5000'],
            'colleagues.*' => ['integer'],
        ];
    }

    /**
     * The colleagues the admin kept on the list for the welcome. Ids only:
     * whose they are is for the scoped query that reads them.
     *
     * @return list<int>
     */
    public function colleagueIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('colleagues');

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Nobody ticked posts nothing at all, so a missing list is an empty one.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['colleagues' => $this->input('colleagues', [])]);
    }
}
