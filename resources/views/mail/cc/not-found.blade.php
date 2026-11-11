<x-mail.frame :title="__('No answer yet')" :preheader="__('The knowledge base does not know about this yet.')" :label="__('No answer yet')">
<h1 class="hm-ink" style="margin: 0 0 24px; font-size: 24px; line-height: 31px; font-weight: 800; letter-spacing: -0.4px; color: #0d1b1e;">{{ __('The knowledge base does not know about this yet.') }}</h1>
@include('mail.cc.partials.quote', ['label' => __('You asked'), 'text' => $question])
<div class="hm-ink" style="margin: 0; font-size: 16px; line-height: 26px; color: #0d1b1e;">{{ __('Your question is saved with this email, so someone can pick it up. Once the answer is in the knowledge base, Howy knows it next time.') }}</div>
</x-mail.frame>
