<div style="margin: 32px 0 12px;">
@if ($block['eyebrow'] ?? null)
<div class="hm-muted" style="margin: 0 0 6px; font-size: 12px; line-height: 16px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; color: #55636a;">{{ $block['eyebrow'] }}</div>
@endif
<h2 class="hm-ink" style="margin: 0; font-size: 22px; line-height: 28px; font-weight: 800; letter-spacing: -0.4px; color: #0d1b1e;">{{ \App\Mail\Content\MailRenderer::inline($block['text']) }}</h2>
</div>
