@extends('layouts.app')
@section('content')
<div class="mb-7 flex flex-wrap items-center justify-between gap-4">
    <div><h1 class="text-3xl font-bold">پروژه‌های پیشنهادی</h1><p class="mt-2 text-slate-500">رتبه‌بندی توضیح‌پذیر پروژه‌های mock</p></div>
    @if($profile)<form method="post" action="{{ route('recommendations.recalculate') }}">@csrf<button class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white">محاسبه مجدد</button></form>@endif
</div>
@if(!$profile || !$profile->enabled)
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-8 text-amber-900">matching فعال نیست. ابتدا <a class="font-bold underline" href="{{ route('matching.settings') }}">تنظیمات matching</a> را تکمیل و فعال کنید.</div>
@else
    <div class="mb-5 flex flex-wrap gap-3"><a href="{{ route('recommendations') }}" class="rounded-lg bg-orange-100 px-4 py-2 text-sm">امتیاز بالاتر از {{ $threshold }}</a><a href="{{ route('recommendations',['all'=>1]) }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm">نمایش همه</a><a href="{{ route('recommendations',['sort'=>'score_asc']) }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm">کمترین امتیاز</a><a href="{{ route('recommendations',['sort'=>'newest']) }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm">جدیدترین</a></div>
    @if($matches->isEmpty())
        <div class="rounded-2xl bg-white p-10 text-center text-slate-500">پروژه‌ای با حداقل امتیاز انتخاب‌شده پیدا نشد.</div>
    @else
        <div class="grid gap-5 lg:grid-cols-2">
            @foreach($matches as $match)
                <article class="rounded-2xl border bg-white p-6 shadow-sm"><div class="flex items-start justify-between gap-4"><h2 class="text-xl font-bold"><a href="{{ route('projects.show',$match->project) }}" class="hover:text-orange-600">{{ $match->project->title }}</a></h2><span class="rounded-full bg-orange-100 px-3 py-1 text-lg font-bold text-orange-700">{{ $match->score }}/۱۰۰</span></div><p class="mt-3 text-slate-600">بودجه: {{ $match->project->budget !== null ? number_format((float)$match->project->budget).' '.($match->project->currency ?? '') : 'نامشخص' }} · وضعیت: {{ $match->project->decision_status }}</p><p class="mt-4 leading-7 text-slate-700">{{ $match->breakdown['explanation'] ?? 'بدون توضیح' }}</p><div class="mt-4 text-sm text-slate-500">مهارت‌های منطبق: {{ implode('، ', $match->breakdown['matched_skills'] ?? []) ?: 'ندارد' }}</div></article>
            @endforeach
        </div>
        <div class="mt-6">{{ $matches->links() }}</div>
    @endif
@endif
@endsection
