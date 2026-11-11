@php($rules = $block['cards'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 4px 0 12px;">
<tr>
@foreach ($rules as $index => $rule)
<td class="hm-stack" width="{{ count($rules) === 2 ? '50%' : '100%' }}" valign="top" style="padding: 0 {{ count($rules) === 2 && $index === 0 ? '6px' : '0' }} 0 {{ count($rules) === 2 && $index === 1 ? '6px' : '0' }};">
<table role="presentation" class="hm-raised" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #edf1ef; border-radius: 12px;">
<tr>
<td style="padding: 20px 20px 18px;">
<div class="hm-ink" style="font-size: 30px; line-height: 34px; font-weight: 800; letter-spacing: -0.8px; color: #0d1b1e;"><span style="border-bottom: 4px solid #cdf464;">{{ $rule['field'] }}</span></div>
<div class="hm-ink" style="padding-top: 12px; font-size: 16px; line-height: 22px; font-weight: 700; color: #0d1b1e;">{{ $rule['title'] }}</div>
<div class="hm-muted" style="padding-top: 6px; font-size: 14px; line-height: 21px; color: #55636a;">{{ $rule['text'] }}</div>
</td>
</tr>
</table>
</td>
@endforeach
</tr>
</table>
<p class="hm-muted" style="margin: 0 0 20px; font-size: 14px; line-height: 22px; color: #55636a;">{{ __('My address:') }} <a class="hm-link" href="mailto:{{ $block['address'] }}" style="color: #2f6b1f; font-weight: 700; text-decoration: none;">{{ $block['address'] }}</a></p>
