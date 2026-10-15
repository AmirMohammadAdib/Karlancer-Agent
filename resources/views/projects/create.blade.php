@extends('layouts.app')
@section('content')
<h1 class="mb-7 text-3xl font-bold">ایجاد پروژه آزمایشی</h1>
<form method="post" action="{{ route('projects.store') }}" class="max-w-3xl space-y-5 rounded-2xl bg-white p-6 shadow-sm">@csrf
@if($errors->any())<div class="rounded-lg bg-red-50 p-4 text-red-700"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div><label class="mb-2 block">عنوان پروژه</label><input name="title" value="{{ old('title') }}" required class="w-full rounded-lg border p-3"></div>
<div><label class="mb-2 block">توضیحات</label><textarea name="description" rows="6" required class="w-full rounded-lg border p-3">{{ old('description') }}</textarea></div>
<div class="grid gap-4 sm:grid-cols-2"><div><label class="mb-2 block">بودجه</label><input name="budget" type="number" min="0" step="0.01" value="{{ old('budget') }}" class="w-full rounded-lg border p-3"></div><div><label class="mb-2 block">واحد پول</label><select name="currency" required class="w-full rounded-lg border p-3"><option value="">انتخاب کنید</option>@foreach(['IRR'=>'ریال','USD'=>'دلار','EUR'=>'یورو'] as $key=>$label)<option value="{{ $key }}" @selected(old('currency')===$key)>{{ $label }}</option>@endforeach</select></div></div>
<div><label class="mb-2 block">مهارت‌های موردنیاز</label><input name="required_skills" value="{{ old('required_skills') }}" placeholder="Laravel، MySQL، UI/UX" class="w-full rounded-lg border p-3"><p class="mt-1 text-xs text-slate-500">مهارت‌ها را با ویرگول جدا کنید.</p></div>
<div class="flex gap-3"><button class="rounded-lg bg-orange-500 px-5 py-3 font-bold text-white">ذخیره پروژه</button><a href="{{ route('projects.index','new') }}" class="rounded-lg bg-slate-100 px-5 py-3">انصراف</a></div></form>
@endsection
