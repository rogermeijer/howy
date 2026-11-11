<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 4px 0 24px;">
<tr>
@foreach ($block['stats'] as $index => $stat)
<td class="hm-stack" width="{{ floor(100 / max(1, count($block['stats']))) }}%" valign="top" style="padding: 0 {{ $index < count($block['stats']) - 1 ? '6px' : '0' }} 0 {{ $index > 0 ? '6px' : '0' }};">
<table role="presentation" class="hm-raised" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #edf1ef; border-radius: 12px;">
<tr>
<td style="padding: 16px;">
<div class="hm-ink" style="font-size: 28px; line-height: 32px; font-weight: 800; letter-spacing: -0.6px; color: #0d1b1e;"><span style="border-bottom: 4px solid #cdf464;">{{ $stat['value'] }}</span></div>
<div class="hm-muted" style="padding-top: 10px; font-size: 13px; line-height: 19px; color: #55636a;">{{ $stat['label'] }}</div>
</td>
</tr>
</table>
</td>
@endforeach
</tr>
</table>
