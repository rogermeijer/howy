{{--
    The frame of every mail Howy sends: the wordmark, a label that says what
    kind of mail it is, the content, a footer under the card. Tables and inline
    styles, because that is what mail clients render reliably. No images and
    nothing loaded from elsewhere: the wordmark is type with a lime dot, so the
    mail looks the same where images are blocked and carries no attachment.
    Light is the base; clients that honour prefers-color-scheme (Apple Mail,
    iOS, Outlook for Mac) get the dark tokens through the hm-* classes, and
    clients that invert colours themselves have no image to spoil.
--}}
@props([
    'title' => '',
    'preheader' => '',
    'label' => null,
    'labelTone' => 'neutral',
    'account' => null,
    'reason' => null,
    'unsubscribeUrl' => null,
])
@php
    $font = "'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif";
    [$labelBackground, $labelColor] = match ($labelTone) {
        'good' => ['#e4f3ea', '#1e6e47'],
        'accent' => ['#f0fbd3', '#2f6b1f'],
        'warn' => ['#fde9df', '#9a3d12'],
        default => ['#edf1ef', '#55636a'],
    };
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ $title }}</title>
<style>
:root { color-scheme: light dark; supported-color-schemes: light dark; }
body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; }
table { border-collapse: collapse; }
a { color: #2f6b1f; }
@media only screen and (max-width: 620px) {
    .hm-px { padding-left: 24px !important; padding-right: 24px !important; }
    .hm-h1 { font-size: 28px !important; line-height: 34px !important; }
    .hm-stack { display: block !important; width: 100% !important; padding: 0 0 12px 0 !important; }
    .hm-wide { display: none !important; }
}
@media (prefers-color-scheme: dark) {
    .hm-body { background-color: #0d1b1e !important; }
    .hm-card { background-color: #14272b !important; border-color: #2a4146 !important; }
    .hm-raised { background-color: #1e3439 !important; border-color: #2a4146 !important; }
    .hm-ink { color: #f4f6f5 !important; }
    .hm-muted { color: #a9b6b9 !important; }
    .hm-faint { color: #84919a !important; }
    .hm-line { border-color: #2a4146 !important; }
    .hm-link { color: #cdf464 !important; }
    .hm-deep { color: #cdf464 !important; }
    .hm-dot { border-color: #f4f6f5 !important; }
    .hm-pill { background-color: #1e3439 !important; color: #a9b6b9 !important; }
}
[data-ogsb] .hm-body { background-color: #0d1b1e !important; }
[data-ogsb] .hm-card { background-color: #14272b !important; }
[data-ogsb] .hm-raised { background-color: #1e3439 !important; }
[data-ogsc] .hm-ink { color: #f4f6f5 !important; }
[data-ogsc] .hm-muted { color: #a9b6b9 !important; }
[data-ogsc] .hm-link, [data-ogsc] .hm-deep { color: #cdf464 !important; }
</style>
</head>
<body class="hm-body" style="margin: 0; padding: 0; background-color: #f4f6f5;">
@if ($preheader !== '')
<div style="display: none; max-height: 0; overflow: hidden; mso-hide: all; font-size: 1px; line-height: 1px; color: #f4f6f5; opacity: 0;">{{ $preheader }}&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>
@endif
<table role="presentation" class="hm-body" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f6f5;">
<tr>
<td align="center" style="padding: 32px 12px;">
<table role="presentation" class="hm-card" width="600" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 600px; background-color: #ffffff; border: 1px solid #e3e8e6; border-radius: 16px; font-family: {{ $font }}; color: #0d1b1e;">
<tr>
<td class="hm-px" style="padding: 28px 40px 0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td valign="middle" style="font-family: {{ $font }};"><span class="hm-ink" style="font-size: 26px; line-height: 30px; font-weight: 800; letter-spacing: -1.2px; color: #0d1b1e;">Howy</span><span class="hm-dot" style="display: inline-block; width: 7px; height: 7px; margin-left: 3px; border-radius: 50%; background-color: #cdf464; border: 1px solid #0d1b1e;"></span>@if ($account)<span class="hm-muted hm-wide" style="padding-left: 10px; font-size: 13px; line-height: 30px; color: #55636a; white-space: nowrap;">&times; {{ $account }}</span>@endif</td>
@if ($label)
<td align="right" valign="middle"><span class="hm-pill" style="display: inline-block; padding: 5px 11px; border-radius: 999px; background-color: {{ $labelBackground }}; color: {{ $labelColor }}; font-size: 12px; line-height: 16px; font-weight: 600; white-space: nowrap;">{{ $label }}</span></td>
@endif
</tr>
</table>
</td>
</tr>
<tr>
<td class="hm-px" style="padding: 28px 40px 36px;">
{{ $slot }}
</td>
</tr>
</table>
@if ($reason)
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 600px; font-family: {{ $font }};">
<tr>
<td class="hm-px hm-faint" style="padding: 20px 40px 0; font-size: 12px; line-height: 18px; color: #84919a;">
{{ \App\Mail\Content\MailRenderer::inline($reason) }}<br>
<a class="hm-faint" href="{{ \App\Mail\Content\MailUrl::route('privacy-policy') }}" style="color: #84919a; text-decoration: underline;">{{ __('Privacy policy') }}</a>@if ($unsubscribeUrl) &nbsp;·&nbsp; <a class="hm-faint" href="{{ $unsubscribeUrl }}" style="color: #84919a; text-decoration: underline;">{{ __('Unsubscribe from these emails') }}</a>@endif
</td>
</tr>
</table>
@endif
</td>
</tr>
</table>
</body>
</html>
