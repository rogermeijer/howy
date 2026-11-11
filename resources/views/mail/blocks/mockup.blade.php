<table role="presentation" class="hm-card" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 4px 0 {{ ($block['reply'] ?? null) ? '10px' : '24px' }}; background-color: #ffffff; border: 1px solid #cbd3d0; border-radius: 12px;">
@foreach ($block['rows'] as $row)
<tr>
<td class="hm-line hm-muted" width="96" valign="top" style="width: 96px; padding: 10px 0 10px 16px;@unless ($loop->first) border-top: 1px solid #e3e8e6;@endunless font-size: 14px; line-height: 20px; color: #55636a;">{{ $row['label'] }}</td>
<td class="hm-line" valign="top" style="padding: 10px 16px 10px 8px;@unless ($loop->first) border-top: 1px solid #e3e8e6;@endunless font-size: 14px; line-height: 20px;">@if ($row['marked'] ?? false)<span style="background-color: #ffe14d; color: #0d1b1e; padding: 1px 6px; border-radius: 4px; font-weight: 700;">{{ $row['value'] }}</span>@else<span class="hm-ink" style="color: #0d1b1e; font-weight: 600;">{{ $row['value'] }}</span>@endif</td>
</tr>
@endforeach
@if ($block['body'] ?? null)
<tr>
<td class="hm-muted hm-line" colspan="2" style="padding: 12px 16px;@if ($block['rows'] !== []) border-top: 1px solid #e3e8e6;@endif font-size: 14px; line-height: 21px; color: #55636a;">{{ \App\Mail\Content\MailRenderer::inline($block['body']) }}</td>
</tr>
@endif
</table>
@if ($block['reply'] ?? null)
<table role="presentation" class="hm-raised" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px; background-color: #f0fbd3; border-radius: 12px;">
<tr>
<td class="hm-ink" style="padding: 14px 16px; font-size: 14px; line-height: 21px; color: #0d1b1e;">{{ \App\Mail\Content\MailRenderer::inline($block['reply']) }}</td>
</tr>
</table>
@endif
