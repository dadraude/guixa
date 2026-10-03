<x-mail::message>
# {{ __('Log in to :app', ['app' => config('app.name')]) }}

{{ __('Use this button to log in. The link works only once and expires in :minutes minutes.', ['minutes' => $minutes]) }}

<x-mail::button :url="$url">
{{ __('Log in') }}
</x-mail::button>

{{ __('If you did not ask to log in, you can ignore this email.') }}
</x-mail::message>
