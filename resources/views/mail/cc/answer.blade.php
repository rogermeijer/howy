<x-mail.frame :title="__('Answer from the knowledge base')" :preheader="$preheader" :label="__('Answer from the knowledge base')" label-tone="good">
@include('mail.cc.partials.quote', ['label' => __('You asked'), 'text' => $question])
<div class="hm-ink" style="margin: 0 0 24px; font-size: 17px; line-height: 27px; color: #0d1b1e;">{!! nl2br(e($answer)) !!}</div>
@if ($gaps)
@include('mail.cc.partials.gaps', ['gaps' => $gaps])
@endif
@include('mail.cc.partials.sources', ['sources' => $sources])
@if ($rating !== null)
@include('mail.blocks.choices', ['block' => $rating])
@endif
@include('mail.cc.partials.hint', ['text' => __('Is something not right, or do you know more? Just reply to this email.')])
</x-mail.frame>
