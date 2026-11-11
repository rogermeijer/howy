@if ($block['muted'] ?? false)
<p class="hm-muted" style="margin: 0 0 16px; font-size: 14px; line-height: 22px; color: #55636a;">{{ \App\Mail\Content\MailRenderer::inline($block['text']) }}</p>
@else
<p class="hm-ink" style="margin: 0 0 16px; font-size: 16px; line-height: 26px; color: #0d1b1e;">{{ \App\Mail\Content\MailRenderer::inline($block['text']) }}</p>
@endif
