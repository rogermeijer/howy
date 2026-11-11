<div style="margin: 0 0 24px;">
@if ($block['title'] ?? null)
<p class="hm-ink" style="margin: 0 0 10px; font-size: 16px; line-height: 24px; font-weight: 700; color: #0d1b1e;">{{ $block['title'] }}</p>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
@foreach ($block['rows'] as $index => $row)
<tr>
<td class="hm-line" width="36" valign="top" style="padding: 12px 0; border-top: 1px solid #e3e8e6;"><span style="display: inline-block; width: 24px; line-height: 24px; text-align: center; border-radius: 6px; background-color: #0d1b1e; color: #ffffff; font-size: 12px; font-weight: 700;">{{ $index + 1 }}</span></td>
<td class="hm-line" valign="top" style="padding: 12px 0; border-top: 1px solid #e3e8e6;">
<div class="hm-ink" style="font-size: 15px; line-height: 22px; font-weight: 700; color: #0d1b1e;">{{ $row['title'] }}</div>
@if ($row['meta'] ?? null)
<div class="hm-muted" style="font-size: 13px; line-height: 19px; color: #55636a;">{{ $row['meta'] }}</div>
@endif
</td>
<td class="hm-line hm-deep" align="right" valign="top" style="padding: 12px 0 12px 12px; border-top: 1px solid #e3e8e6; font-size: 14px; line-height: 22px; font-weight: 700; color: #2f6b1f; white-space: nowrap;">{{ $row['value'] ?? '' }}</td>
</tr>
@endforeach
</table>
</div>
