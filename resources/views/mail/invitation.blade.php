<x-mail::message>
# Hello {{ $name }}

A manager of the cooperative has invited you to Guixa. Set your password to start ordering.

<x-mail::button :url="$acceptUrl">
Set your password
</x-mail::button>

The link works until {{ $expiresAt->format('j F Y, H:i') }}. If you ask for a new invitation, this link stops working.
</x-mail::message>
