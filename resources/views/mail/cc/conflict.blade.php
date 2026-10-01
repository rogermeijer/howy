@extends('mail.cc.layout', ['badge' => __('Waiting for approval'), 'badgeBackground' => '#ffe4ec', 'badgeColor' => '#b8134f'])

@section('title', __('Waiting for approval'))

@section('content')
<div style="margin: 0 0 24px;">
<h1 style="margin: 0; font-size: 24px; line-height: 1.3; font-weight: 600; letter-spacing: -0.015em; color: #17140f;">{{ $heading }}</h1>
@if ($addedLine)
<div style="margin-top: 10px; font-size: 15px; line-height: 1.55; color: #5c5750;">{{ $addedLine }}</div>
@endif
</div>

@foreach ($conflicts as $conflict)
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px; background: #ffffff; border: 1px solid #e4e0d6; border-radius: 12px;">
<tr>
<td style="padding: 18px 20px; border-bottom: 1px solid #e4e0d6;">
@include('mail.cc.partials.label', ['text' => __('You wrote'), 'color' => '#b8134f'])
<div style="font-size: 16px; line-height: 1.55; color: #17140f;">{{ $conflict['statement'] }}</div>
</td>
</tr>
@if ($conflict['existing'])
<tr>
<td style="padding: 18px 20px; background: #fbfaf7; border-bottom: 1px solid #e4e0d6;">
@include('mail.cc.partials.label', ['text' => __('The knowledge base says')])
<div style="font-size: 16px; line-height: 1.55; color: #17140f;">{{ $conflict['existing'] }}</div>
@if ($conflict['source'])
<div style="margin-top: 8px; font-size: 13px; line-height: 1.4; color: #5c5750;">{{ $conflict['source']['label'] }}</div>
@endif
</td>
</tr>
@endif
@if ($conflict['explanation'])
<tr>
<td style="padding: 16px 20px; font-size: 14px; line-height: 1.55; color: #5c5750;">{{ $conflict['explanation'] }}</td>
</tr>
@endif
</table>
@endforeach

<div style="margin: 0 0 24px;">
@include('mail.cc.partials.label', ['text' => __('What now')])
<div style="font-size: 15px; line-height: 1.6; color: #17140f;">{{ __('Your change is ready for approval in cc:. If it is approved, it replaces what the knowledge base says now. Until then, the current version stands.') }}</div>
</div>

@foreach ($added as $statement)
<div style="margin: 0 0 12px; padding: 14px 18px; background: #e4f3ea; border-radius: 12px; font-size: 14px; line-height: 1.5; color: #1e6e47;">{{ __('Added:') }} <span style="color: #17140f;">{{ $statement }}</span></div>
@endforeach
@if ($added !== [])
<div style="height: 12px; line-height: 12px;">&nbsp;</div>
@endif
@endsection
