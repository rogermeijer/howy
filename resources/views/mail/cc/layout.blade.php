{{--
    The frame of every mail Howy sends: the wordmark, a label that says what
    kind of mail it is, the content and an optional footer. Tables and inline
    styles, because that is what mail clients render reliably; Source Sans 3
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
<link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600&amp;family=Yeseva+One&amp;display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: #ecebe6;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background: #ecebe6;">
<tr>
<td align="center" style="padding: 24px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; background: #f8f6f1; border: 1px solid #e4e0d6; border-radius: 16px; font-family: 'Source Sans 3', Helvetica, Arial, sans-serif; color: #17140f;">
<tr>
<td style="padding: 20px 32px; border-bottom: 1px solid #e4e0d6;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td style="font-family: 'Yeseva One', Georgia, serif; font-size: 26px; line-height: 1; color: #17140f;"><img src="{{ $logo }}" width="76" height="36" alt="Howy" style="display: block; width: 76px; height: 36px; border: 0; font-family: 'Yeseva One', Georgia, serif; font-size: 26px; line-height: 36px; color: #17140f;"></td>
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
<div style="padding-top: 16px; border-top: 1px solid #e4e0d6; font-size: 13px; line-height: 1.5; color: #6b655c;">
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
