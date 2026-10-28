<x-mail::message>
# {{ __('Hello :name', ['name' => $name]) }}

{{ __('A manager of the cooperative has invited you to Guixa. Set your password to start ordering.') }}

<x-mail::button :url="$acceptUrl">
{{ __('Set your password') }}
</x-mail::button>

{{ __('The link works until :date. If you ask for a new invitation, this link stops working.', ['date' => $expiry]) }}
</x-mail::message>
