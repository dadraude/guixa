<x-mail::message>
# {{ __('Invitation to join :app', ['app' => config('app.name')]) }}

@if ($name)
{{ __('Hello :name,', ['name' => $name]) }}

@endif
{{ __('You have been invited to join the household :household. Use this button to confirm your name and choose a password. The invitation works only once and is valid for :days days.', ['household' => $householdName, 'days' => $days]) }}

<x-mail::button :url="$url">
{{ __('Accept invitation') }}
</x-mail::button>

{{ __('If you were not expecting this invitation, you can ignore this email.') }}
</x-mail::message>
