@if ($sources !== [])
<div style="margin: 0 0 24px;">
@include('mail.cc.partials.label', ['text' => count($sources) === 1 ? __('Source') : __('Sources')])
@foreach ($sources as $source)
<div style="margin: 0 0 8px; padding: 12px 14px; background: #ffffff; border: 1px solid #e3e8e6; border-radius: 10px;">
<div style="font-size: 14px; line-height: 1.4; font-weight: 600; color: #0d1b1e;">{{ $source['title'] }}</div>
@if ($source['detail'])
<div style="margin-top: 2px; font-size: 13px; line-height: 1.4; color: #5f6d73;">{{ $source['detail'] }}</div>
@endif
</div>
@endforeach
</div>
@endif
