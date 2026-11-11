<div style="margin: 0 0 24px;">
@if ($block['title'] ?? null)
<p class="hm-ink" style="margin: 0 0 10px; font-size: 16px; line-height: 24px; font-weight: 700; color: #0d1b1e;">{{ $block['title'] }}</p>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
@foreach ($block['items'] as $item)
<tr>
<td class="hm-line" width="34" valign="top" style="padding: 12px 0; border-top: 1px solid #e3e8e6;"><span class="hm-line" style="display: inline-block; width: 18px; height: 18px; border: 2px solid #0d1b1e; border-radius: 5px;">&nbsp;</span></td>
<td class="hm-line hm-ink" valign="top" style="padding: 12px 0; border-top: 1px solid #e3e8e6; font-size: 15px; line-height: 22px; color: #0d1b1e;">{{ \App\Mail\Content\MailRenderer::inline($item['text']) }}</td>
<td class="hm-line hm-deep" align="right" valign="top" style="padding: 12px 0 12px 12px; border-top: 1px solid #e3e8e6; font-size: 14px; line-height: 22px; font-weight: 700; color: #2f6b1f; white-space: nowrap;">{{ $item['value'] ?? '' }}</td>
</tr>
@endforeach
</table>
</div>
