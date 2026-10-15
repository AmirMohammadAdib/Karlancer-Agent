<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'currency' => ['required', 'string', 'in:IRR,USD,EUR'],
            'required_skills' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'عنوان پروژه',
            'description' => 'توضیحات پروژه',
            'budget' => 'بودجه',
            'currency' => 'واحد پول',
            'required_skills' => 'مهارت‌های موردنیاز',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute الزامی است.',
            'string' => ':attribute باید متنی باشد.',
            'numeric' => ':attribute باید عددی باشد.',
            'min' => ':attribute نمی‌تواند منفی باشد.',
            'max' => 'طول یا مقدار :attribute بیش از حد مجاز است.',
            'currency.in' => 'واحد پول انتخاب‌شده معتبر نیست.',
        ];
    }
}
