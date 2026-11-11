@php
    [$background, $border, $titleColor, $titleClass, $boxClass] = match ($block['tone'] ?? 'panel') {
        'dashed' => ['#ffffff', '2px dashed #cdf464', '#55636a', 'hm-muted', 'hm-card'],
        'note' => ['#f0fbd3', '0', '#2f6b1f', 'hm-deep', 'hm-raised'],
        'warn' => ['#fde9df', '0', '#9a3d12', 'hm-ink', 'hm-raised'],
        default => ['#edf1ef', '0', '#55636a', 'hm-muted', 'hm-raised'],
    };
@endphp
<table role="presentation" class="{{ $boxClass }}" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 8px 0 24px; background-color: {{ $background }}; border: {{ $border }}; border-radius: 12px;">
<tr>
<td style="padding: 18px 22px;">
@if ($block['title'] ?? null)
<p class="{{ $titleClass }}" style="margin: 0 0 8px; font-size: 12px; line-height: 16px; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; color: {{ $titleColor }};">{{ $block['title'] }}</p>
@endif
@if (($block['text'] ?? '') !== '')
<p class="hm-ink" style="margin: 0; font-size: 15px; line-height: 24px; color: #0d1b1e;">{{ \App\Mail\Content\MailRenderer::inline($block['text']) }}</p>
@endif
@if (($block['items'] ?? []) !== [])
<div style="padding-top: {{ ($block['text'] ?? '') !== '' ? '12px' : '0' }};">
@include('mail.blocks.partials.bullets', ['items' => $block['items']])
</div>
@endif
</td>
</tr>
</table>
