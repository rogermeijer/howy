@extends('mail.cc.layout', ['badge' => __('No answer yet'), 'badgeBackground' => '#ecebe6', 'badgeColor' => '#5c5750'])

@section('title', __('No answer yet'))

@section('content')
<h1 style="margin: 0 0 24px; font-size: 24px; line-height: 1.3; font-weight: 600; letter-spacing: -0.015em; color: #17140f;">{{ __('The knowledge base does not know about this yet.') }}</h1>
@include('mail.cc.partials.quote', ['label' => __('You asked'), 'text' => $question])
<div style="margin: 0 0 24px; font-size: 16px; line-height: 1.6; color: #17140f;">{{ __('Your question is saved with this email, so someone can pick it up. Once the answer is in the knowledge base, Howy knows it next time.') }}</div>
@endsection
