@php($denied = session()->pull(\App\Http\Responses\FilamentAccessDeniedRedirect::SESSION_KEY))
@if (filled($denied))
    <div
        role="alert"
        class="fi-access-denied-banner mx-4 mt-4 rounded-lg border border-danger-600/20 bg-danger-600/10 px-4 py-3 text-sm text-danger-800 dark:text-danger-200"
    >
        {{ $denied }}
    </div>
@endif
