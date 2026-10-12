<x-mail::message>
# {{ __('You\'re in') }}

{{ __('Thanks for your patience. There is room for your team in the Howy private beta now.') }}

{{ __('Your invite code is:') }}

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

<x-mail::button :url="$url">
{{ __('Create your account') }}
</x-mail::button>

{{ __('The code works once: use it to create your team\'s account.') }}

{{ __('See you in your threads,') }}<br>
{{ __('The Howy team') }}
</x-mail::message>
