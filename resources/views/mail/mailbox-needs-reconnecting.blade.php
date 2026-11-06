<x-mail::message>
# {{ __(':address is no longer connected', ['address' => $address]) }}

{{ __('Google no longer gives Howy access to :address, so new emails there are not coming into :account.', ['address' => $address, 'account' => $accountName]) }}

{{ __('This happens when access was removed in the Google account, its password was changed, or the mailbox was disconnected from Howy elsewhere.') }}

<x-mail::button :url="$url">
{{ __('Connect it again') }}
</x-mail::button>

{{ __('The emails Howy already stored stay where they are. Once the mailbox is connected again, Howy catches up on recent mail; older emails can be imported.') }}

{{ __('The Howy team') }}
</x-mail::message>
