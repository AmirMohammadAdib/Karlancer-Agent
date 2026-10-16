@extends('layouts.app')
@section('content')
<h1 class="text-2xl font-bold mb-6">تاریخچه ورود پروژه</h1>
<div class="space-y-3">
@forelse($runs as $run)
    <div class="rounded-xl bg-white border p-4"><div class="font-bold">{{ $run->source }} — {{ $run->status }}</div><div class="text-sm text-slate-600 mt-2">شروع: {{ optional($run->started_at)->format('Y-m-d H:i') }} | بررسی اعلان: {{ $run->notifications_inspected ?? 0 }} | دریافت: {{ $run->projects_fetched }} | ایجاد: {{ $run->projects_created }} | به‌روزرسانی: {{ $run->projects_updated }} | بدون تغییر: {{ $run->projects_skipped }} | رد اعتبارسنجی: {{ $run->projects_rejected ?? 0 }} | خطای پردازش: {{ $run->projects_failed ?? 0 }}</div>@if(count($run->errors ?? []))<div class="mt-2 text-sm text-rose-700">خطاهای ثبت‌شده: {{ implode('، ', $run->errors) }}</div>@endif</div>
@empty
    <div class="rounded-xl border bg-white p-6">هنوز اجرای ورودی ثبت نشده است.</div>
@endforelse
</div><div class="mt-5">{{ $runs->links() }}</div>
@endsection
