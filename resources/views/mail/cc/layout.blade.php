{{--
    The frame of every mail Howy sends: the wordmark, a label that says what
    kind of mail it is, the content and an optional footer. Tables and inline
    styles, because that is what mail clients render reliably; Plus Jakarta Sans
    loads where a client allows web fonts, else Helvetica. The wordmark is an
    embedded image (cid:), with the text as its alt.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>@yield('title')</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: #edf1ef;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #edf1ef;">
<tr>
<td align="center" style="padding: 24px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; background: #ffffff; border: 1px solid #e3e8e6; border-radius: 16px; font-family: 'Plus Jakarta Sans', Helvetica, Arial, sans-serif; color: #0d1b1e;">
<tr>
<td style="padding: 20px 32px; border-bottom: 1px solid #e3e8e6;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td style="font-family: 'Plus Jakarta Sans', Helvetica, Arial, sans-serif; font-size: 26px; line-height: 1; color: #0d1b1e;"><img src="{{ $logo }}" width="83" height="27" alt="Howy" style="display: block; width: 83px; height: 27px; border: 0; font-family: 'Plus Jakarta Sans', Helvetica, Arial, sans-serif; font-size: 26px; line-height: 27px; color: #0d1b1e;"></td>
<td align="right"><span style="display: inline-block; padding: 6px 12px; border-radius: 999px; background: {{ $badgeBackground }}; color: {{ $badgeColor }}; font-size: 12px; line-height: 1.3; font-weight: 600;">{{ $badge }}</span></td>
</tr>
</table>
</td>
</tr>
<tr>
<td style="padding: 28px 32px 4px;">
@yield('content')
</td>
</tr>
@hasSection('footer')
<tr>
<td style="padding: 0 32px 24px;">
<div style="padding-top: 16px; border-top: 1px solid #e3e8e6; font-size: 13px; line-height: 1.5; color: #5f6d73;">
@yield('footer')
</div>
</td>
</tr>
@else
<tr><td style="padding: 0 0 8px;"></td></tr>
@endif
</table>
</td>
</tr>
</table>
</body>
</html>
