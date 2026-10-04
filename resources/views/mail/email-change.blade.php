<x-mail::message>
# {{ __('Confirm your new email address for :app', ['app' => config('app.name')]) }}

{{ __('Use this button to confirm this address as your new email. The link works only once and is valid for :hours hours. Until then, your current email keeps working.', ['hours' => $hours]) }}

<x-mail::button :url="$url">
{{ __('Confirm email address') }}
</x-mail::button>

{{ __('If you did not ask for this change, you can ignore this email.') }}
</x-mail::message>
