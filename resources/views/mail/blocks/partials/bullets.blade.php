<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
@foreach ($items as $item)
<tr>
<td class="hm-deep" width="20" valign="top" style="padding: 0 0 10px; font-size: 16px; line-height: 24px; font-weight: 800; color: #2f6b1f;">&bull;</td>
<td class="hm-ink" valign="top" style="padding: 0 0 10px; font-size: 16px; line-height: 24px; color: #0d1b1e;">{{ \App\Mail\Content\MailRenderer::inline($item) }}</td>
</tr>
@endforeach
</table>
