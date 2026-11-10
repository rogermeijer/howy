import { Form, Link } from '@inertiajs/react';
import { FileText, Plus } from 'lucide-react';
import EmailAttachmentController from '@/actions/App/Http/Controllers/EmailAttachmentController';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import documentRoutes from '@/routes/knowledge/documents';
import type { MailAttachment } from '@/types';

type Props = {
    emailId: number;
    attachment: MailAttachment;
    /** May add an attachment that was left out to the knowledge base. */
    canAdd: boolean;
};

/** English source strings, translated where they are rendered. */
const decisionLabels: Record<string, string> = {
    known: 'Already in the knowledge base',
    ignored: 'Not added',
    unsupported: 'Cannot be read',
    too_large: 'Too large',
    decorative: 'Image too small to read',
    forwarded: 'Read as forwarded mail',
    failed: 'Could not be fetched',
};

/**
 * An attachment and what became of it: the document it is now (new, waiting
 * for approval, or already known), or why it was left out.
 */
export function AttachmentChip({ emailId, attachment, canAdd }: Props) {
    const t = useTranslations();
    const document = attachment.document;

    const status = document
        ? document.status === 'review'
            ? t('Ready for approval')
            : document.status === 'rejected'
              ? t('Rejected')
              : attachment.decision === 'known'
                ? t(decisionLabels.known)
                : document.status === 'ready'
                  ? t('In the knowledge base')
                  : t('Being processed')
        : attachment.decision
          ? t(decisionLabels[attachment.decision] ?? 'Not added')
          : null;

    const size =
        attachment.size >= 1024 * 1024
            ? `${(attachment.size / 1024 / 1024).toFixed(1)} MB`
            : `${Math.max(1, Math.round(attachment.size / 1024))} KB`;

    const chip = (
        <span
            className={cn(
                'flex items-center gap-2.5 rounded-[10px] border-[1.5px] px-3.5 py-2 text-[13px]',
                document?.status === 'review'
                    ? 'border-cc-pending-fg/40 bg-cc-pending-bg/50'
                    : 'border-cc-border',
            )}
        >
            <FileText className="size-4 text-cc-subtle" />
            <span className="font-semibold">{attachment.filename}</span>
            <span className="text-cc-faint">{size}</span>
            {status && (
                <span className="text-[12px] font-medium text-cc-muted">
                    · {status}
                </span>
            )}
        </span>
    );

    return (
        <li className="flex items-center gap-1.5">
            {document ? (
                <Link
                    href={documentRoutes.show(document.id)}
                    className="transition-opacity hover:opacity-80"
                >
                    {chip}
                </Link>
            ) : attachment.reason ? (
                <Tooltip>
                    <TooltipTrigger asChild>{chip}</TooltipTrigger>
                    <TooltipContent className="max-w-[320px]">
                        {attachment.reason}
                    </TooltipContent>
                </Tooltip>
            ) : (
                chip
            )}

            {canAdd && attachment.canAdd && (
                <Form
                    {...EmailAttachmentController.store.form([
                        emailId,
                        attachment.index,
                    ])}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <button
                            type="submit"
                            disabled={processing}
                            aria-label={t('Add :name to the knowledge base', {
                                name: attachment.filename,
                            })}
                            className="flex h-9 cursor-pointer items-center gap-1.5 rounded-[10px] border-[1.5px] border-cc-border bg-cc-panel px-3 text-[12px] font-semibold transition-colors hover:border-cc-border-strong disabled:opacity-50"
                        >
                            <Plus className="size-3.5" strokeWidth={2.5} />
                            {t('Add to knowledge base')}
                        </button>
                    )}
                </Form>
            )}
        </li>
    );
}
