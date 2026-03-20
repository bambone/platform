<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motorcycle_id' => ['nullable', 'exists:motorcycles,id'],
            'rental_date_from' => ['nullable', 'date'],
            'rental_date_to' => ['nullable', 'date', 'after_or_equal:rental_date_from'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', 'string', 'max:50'],
            'page_url' => ['nullable', 'string', 'max:500'],
        ];
    }
}
