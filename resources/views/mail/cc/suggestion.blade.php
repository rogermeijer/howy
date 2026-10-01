@extends('mail.cc.layout', ['badge' => __('Only for you'), 'badgeBackground' => '#e6eefb', 'badgeColor' => '#1f5bb8'])

@section('title', __('Suggestion for your answer'))

@section('content')
<div style="margin: 0 0 24px;">
@include('mail.cc.partials.label', ['text' => __('Suggestion for your answer')])
<h1 style="margin: 0; font-size: 24px; line-height: 1.3; font-weight: 600; letter-spacing: -0.015em; color: #17140f;">{{ $gaps ? __(':name asked you something the knowledge base partly knows the answer to.', ['name' => $asker]) : __(':name asked you something the knowledge base knows the answer to.', ['name' => $asker]) }}</h1>
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px; background: #ffffff; border: 1px solid #e4e0d6; border-radius: 12px;">
<tr>
<td width="36" valign="top" style="padding: 16px 0 16px 18px;">
<div style="width: 36px; height: 36px; border-radius: 18px; background: #ecebe6; text-align: center; font-size: 13px; line-height: 36px; font-weight: 600; color: #17140f;">{{ $initials }}</div>
</td>
<td valign="top" style="padding: 16px 18px 16px 14px;">
<div style="font-size: 13px; line-height: 1.4; color: #6b655c;"><span style="font-weight: 600; color: #17140f;">{{ $asker }}</span>@if ($askedAt) · {{ $askedAt }}@endif</div>
<div style="margin-top: 4px; font-size: 15px; line-height: 1.55; color: #17140f;">{{ $question }}</div>
</td>
</tr>
</table>

<div style="margin: 0 0 24px;">
@include('mail.cc.partials.label', ['text' => __('Possible answer')])
<div style="font-size: 17px; line-height: 1.6; color: #17140f;">{!! nl2br(e($answer)) !!}</div>
</div>

@if ($gaps)
@include('mail.cc.partials.gaps', ['gaps' => $gaps, 'invite' => __('Do you know? Put it in your answer to :name and keep cc: in CC, so the knowledge base learns it right away.', ['name' => $asker])])
@endif

@include('mail.cc.partials.sources', ['sources' => $sources])
@endsection

@section('footer')
<div>{{ __(':name does not see this suggestion. cc: only sends it to whoever was asked.', ['name' => $asker]) }}</div>
<div style="margin-top: 6px;">{{ __('Confidence: :percent%', ['percent' => $confidence]) }}</div>
@endsection
