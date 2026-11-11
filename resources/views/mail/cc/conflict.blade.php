<x-mail.frame :title="__('Waiting for approval')" :preheader="$heading" :label="__('Waiting for approval')" label-tone="accent">
<div style="margin: 0 0 24px;">
<h1 class="hm-ink" style="margin: 0; font-size: 24px; line-height: 31px; font-weight: 800; letter-spacing: -0.4px; color: #0d1b1e;">{{ $heading }}</h1>
@if ($addedLine)
<div class="hm-muted" style="margin-top: 10px; font-size: 15px; line-height: 23px; color: #55636a;">{{ $addedLine }}</div>
@endif
</div>

@foreach ($conflicts as $conflict)
<table role="presentation" class="hm-card" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px; background-color: #ffffff; border: 1px solid #e3e8e6; border-radius: 12px;">
<tr>
<td class="hm-line" style="padding: 18px 20px; border-bottom: 1px solid #e3e8e6;">
@include('mail.cc.partials.label', ['text' => __('You wrote'), 'color' => '#2f6b1f', 'class' => 'hm-deep'])
<div class="hm-ink" style="font-size: 16px; line-height: 25px; color: #0d1b1e;">{{ $conflict['statement'] }}</div>
</td>
</tr>
@if ($conflict['existing'])
<tr>
<td class="hm-raised hm-line" style="padding: 18px 20px; background-color: #f4f6f5; border-bottom: 1px solid #e3e8e6;">
@include('mail.cc.partials.label', ['text' => __('The knowledge base says')])
<div class="hm-ink" style="font-size: 16px; line-height: 25px; color: #0d1b1e;">{{ $conflict['existing'] }}</div>
@if ($conflict['source'])
<div class="hm-muted" style="margin-top: 8px; font-size: 13px; line-height: 19px; color: #55636a;">{{ $conflict['source']['label'] }}</div>
@endif
</td>
</tr>
@endif
@if ($conflict['explanation'])
<tr>
<td class="hm-muted" style="padding: 16px 20px; font-size: 14px; line-height: 22px; color: #55636a;">{{ $conflict['explanation'] }}</td>
</tr>
@endif
</table>
@endforeach

<div style="margin: 0 0 24px;">
@include('mail.cc.partials.label', ['text' => __('What now')])
<div class="hm-ink" style="font-size: 15px; line-height: 24px; color: #0d1b1e;">{{ __('Your change is ready for approval in Howy. If it is approved, it replaces what the knowledge base says now. Until then, the current version stands.') }}</div>
</div>

@foreach ($added as $statement)
<table role="presentation" class="hm-raised" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 12px; background-color: #f0fbd3; border-radius: 12px;">
<tr>
<td style="padding: 14px 18px; font-size: 14px; line-height: 21px;"><span class="hm-deep" style="font-weight: 700; color: #2f6b1f;">{{ __('Added:') }}</span> <span class="hm-ink" style="color: #0d1b1e;">{{ $statement }}</span></td>
</tr>
</table>
@endforeach
</x-mail.frame>
