<x-mail::message>
# {{ __('Welcome to :app', ['app' => config('app.name')]) }}

{{ __('Hello :name,', ['name' => $name]) }}

{{ __('You have been added to the cooperative. Go to the sign-in screen and ask for a link or a code by email; you will choose a password the first time you get in.') }}

<x-mail::button :url="$url">
{{ __('Go to sign in') }}
</x-mail::button>
</x-mail::message>
