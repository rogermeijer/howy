@if ($sources !== [])
<div style="margin: 0 0 24px;">
@include('mail.cc.partials.label', ['text' => count($sources) === 1 ? __('Source') : __('Sources')])
@foreach ($sources as $source)
<table role="presentation" class="hm-raised" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 8px; background-color: #f4f6f5; border: 1px solid #e3e8e6; border-radius: 10px;">
<tr>
<td style="padding: 12px 14px;">
<div class="hm-ink" style="font-size: 14px; line-height: 20px; font-weight: 700; color: #0d1b1e;">{{ $source['title'] }}</div>
@if ($source['detail'])
<div class="hm-muted" style="margin-top: 2px; font-size: 13px; line-height: 19px; color: #55636a;">{{ $source['detail'] }}</div>
@endif
</td>
</tr>
</table>
@endforeach
</div>
@endif
