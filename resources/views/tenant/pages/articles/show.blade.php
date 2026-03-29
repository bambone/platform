@extends('tenant.layouts.app')

@section('title', optional($article)->title ?? 'Статья')

@section('content')
    <article class="pb-12 pt-24 sm:pb-16 sm:pt-28">
        <div class="mx-auto max-w-4xl px-3 sm:px-4 md:px-8">
            <p class="text-sm leading-relaxed text-silver sm:text-base">Страница статьи</p>
        </div>
    </article>
@endsection
