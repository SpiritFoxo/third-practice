<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetAstroEventsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lat' => 'nullable|numeric|between:-90,90',
            'lon' => 'nullable|numeric|between:-180,180',
            'days' => 'nullable|integer|min:1|max:30',
        ];
    }
}