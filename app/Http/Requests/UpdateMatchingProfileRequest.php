<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMatchingProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['preferred_skills' => ['nullable', 'string', 'max:2000'], 'positive_keywords' => ['nullable', 'string', 'max:2000'], 'negative_keywords' => ['nullable', 'string', 'max:2000'], 'min_budget' => ['nullable', 'numeric', 'min:0'], 'max_budget' => ['nullable', 'numeric', 'min:0', 'gte:min_budget'], 'preferred_currency' => ['nullable', 'string', 'in:IRR,USD,EUR'], 'min_score' => ['required', 'integer', 'between:0,100'], 'enabled' => ['nullable', 'boolean']];
    }

    public function attributes(): array
    {
        return ['preferred_skills' => 'مهارت‌های ترجیحی', 'positive_keywords' => 'کلیدواژه‌های مثبت', 'negative_keywords' => 'کلیدواژه‌های منفی', 'min_budget' => 'حداقل بودجه', 'max_budget' => 'حداکثر بودجه', 'preferred_currency' => 'واحد پول ترجیحی', 'min_score' => 'حداقل امتیاز'];
    }

    public function messages(): array
    {
        return ['required' => ':attribute الزامی است.', 'numeric' => ':attribute باید عددی باشد.', 'min' => ':attribute نمی‌تواند منفی باشد.', 'gte' => 'حداکثر بودجه باید بزرگ‌تر یا مساوی حداقل بودجه باشد.', 'between' => ':attribute باید بین ۰ تا ۱۰۰ باشد.', 'in' => 'مقدار انتخاب‌شده برای :attribute معتبر نیست.'];
    }
}
