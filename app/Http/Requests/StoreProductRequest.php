<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:50', 'unique:products,sku'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:999999'],
            'suppliers' => ['sometimes', 'array'],
            'suppliers.*.id' => ['required', 'integer', 'distinct', 'exists:suppliers,id'],
            'suppliers.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    // normalise here so the unique rule checks the value the model mutator will store
    protected function prepareForValidation(): void
    {
        $sku = $this->input('sku');

        if (is_string($sku)) {
            $this->merge(['sku' => strtoupper(trim($sku))]);
        }
    }
}
