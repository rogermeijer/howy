<table role="presentation" class="hm-raised" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 8px 0 24px; background-color: #edf1ef; border-radius: 12px;">
<tr>
<td style="padding: 18px 22px;">
<p class="hm-ink" style="margin: 0 0 12px; font-size: 16px; line-height: 24px; font-weight: 700; color: #0d1b1e;">{{ $block['question'] }}</p>
<p style="margin: 0;">@foreach ($block['choices'] as $choice)<a class="hm-card hm-ink" href="{{ $choice['url'] }}" style="display: inline-block; margin: 0 8px 8px 0; padding: 9px 16px; background-color: #ffffff; border: 1px solid #cbd3d0; border-radius: 999px; font-size: 14px; line-height: 18px; font-weight: 700; color: #0d1b1e; text-decoration: none;">{{ $choice['label'] }}</a>@endforeach</p>
@if ($block['note'] ?? null)
<p class="hm-muted" style="margin: 6px 0 0; font-size: 13px; line-height: 20px; color: #55636a;">{{ \App\Mail\Content\MailRenderer::inline($block['note']) }}</p>
@endif
</td>
</tr>
</table>
