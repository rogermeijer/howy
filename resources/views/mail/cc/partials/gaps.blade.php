<table role="presentation" class="hm-raised" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px; background-color: #fde9df; border-radius: 12px;">
<tr>
<td style="padding: 16px 20px;">
<div class="hm-ink" style="margin: 0 0 6px; font-size: 14px; line-height: 20px; font-weight: 700; color: #9a3d12;">{{ __('Not in the knowledge base yet') }}</div>
<div class="hm-ink" style="font-size: 15px; line-height: 23px; color: #3d2a06;">{{ $gaps }}</div>
@isset($invite)
<div class="hm-muted" style="margin-top: 8px; font-size: 14px; line-height: 22px; color: #5c4210;">{{ $invite }}</div>
@endisset
</td>
</tr>
</table>
