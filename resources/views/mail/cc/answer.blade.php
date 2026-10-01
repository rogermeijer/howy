@extends('mail.cc.layout', ['badge' => __('Answer from the knowledge base'), 'badgeBackground' => '#e4f3ea', 'badgeColor' => '#1e6e47'])

@section('title', __('Answer from the knowledge base'))

@section('content')
@include('mail.cc.partials.quote', ['label' => __('You asked'), 'text' => $question])
<div style="margin: 0 0 24px; font-size: 17px; line-height: 1.6; color: #17140f;">{!! nl2br(e($answer)) !!}</div>
@if ($gaps)
@include('mail.cc.partials.gaps', ['gaps' => $gaps])
@endif
@include('mail.cc.partials.sources', ['sources' => $sources])
@endsection

@section('footer')
{{ __('Is something not right, or do you know more? Just reply to this email.') }}
@endsection
