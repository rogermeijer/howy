<div style="margin: 0 0 20px;">
@if ($block['title'] ?? null)
<p class="hm-ink" style="margin: 0 0 10px; font-size: 16px; line-height: 24px; font-weight: 700; color: #0d1b1e;">{{ \App\Mail\Content\MailRenderer::inline($block['title']) }}</p>
@endif
@include('mail.blocks.partials.bullets', ['items' => $block['items']])
</div>
