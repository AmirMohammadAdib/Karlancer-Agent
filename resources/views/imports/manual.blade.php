@extends('layouts.app') @section('content')
<h1 class="text-2xl font-bold mb-6">ورود دستی پروژه‌های کارلنسر</h1>
<div class="rounded-xl bg-white border p-6 max-w-2xl"><p class="text-sm text-slate-600 mb-4">اتصال خودکار تأیید نشده است. فایل JSON یا CSV را فقط از یک روش مجاز تهیه و برای پیش‌نمایش بارگذاری کنید.</p><form method="post" action="{{ route('sources.karlancer.manual.preview') }}" enctype="multipart/form-data">@csrf<input type="file" name="file" accept=".json,.csv,.txt" required class="block mb-4"><button class="rounded bg-orange px-4 py-2 text-white">پیش‌نمایش و اعتبارسنجی</button></form>@foreach($errors->all() as $error)<p class="text-red-700 mt-2">{{ $error }}</p>@endforeach</div>
@endsection
