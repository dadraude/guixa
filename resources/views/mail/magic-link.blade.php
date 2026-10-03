<x-mail::message>
# {{ __('Log in to :app', ['app' => config('app.name')]) }}

{{ __('Use this button to log in. The link works only once and expires in :minutes minutes.', ['minutes' => $minutes]) }}

<x-mail::button :url="$url">
{{ __('Log in') }}
</x-mail::button>

@if ($code)
{{ __('Or type this code on the screen where you asked to log in:') }}

<x-mail::panel>
<strong style="font-size: 24px; letter-spacing: 6px;">{{ $code }}</strong>
</x-mail::panel>
@endif

{{ __('If you did not ask to log in, you can ignore this email.') }}
</x-mail::message>
