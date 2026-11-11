{{-- A MailContent, block by block, in the frame. The plain text comes from MailRenderer. --}}
<x-mail.frame
    :title="$content->subject"
    :preheader="$content->preheader"
    :label="$content->label"
    :account="$content->account"
    :reason="$content->footerReason()"
    :unsubscribe-url="$content->unsubscribeUrl()"
>
@foreach ($content->blocks() as $block)
@include('mail.blocks.'.$block['type'], ['block' => $block['data']])
@endforeach
</x-mail.frame>
