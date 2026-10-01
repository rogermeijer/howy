import { Link } from '@inertiajs/react';
import { FileText, Mail } from 'lucide-react';
import { useTranslations } from '@/hooks/use-translations';
import emailRoutes from '@/routes/emails';
import documentRoutes from '@/routes/knowledge/documents';

type Props = {
    documentId: number | null;
    title: string | null;
    versionNumber?: number;
    headingPath?: string | null;
    pageFrom?: number | null;
    sectionId?: number | null;
    emailId?: number | null;
};

/**
 * Where something comes from, as a link: "Handboek · v2 · §Verlof › Vakantie
 * · p. 4" opens the document inspector at that section.
 */
export function SourceChip({
    documentId,
    title,
    versionNumber,
    headingPath,
    pageFrom,
    sectionId,
}: Props) {
    const t = useTranslations();

    if (documentId === null) {
        return null;
    }

    const lastHeading = headingPath?.split(' › ').at(-1);

    return (
        <Link
            href={documentRoutes.show(documentId, {
                query: sectionId ? { section: sectionId } : undefined,
            })}
            className="inline-flex max-w-full items-center gap-1.5 rounded-md border border-cc-border bg-cc-panel px-2 py-1 text-[12px] font-medium text-cc-muted transition-colors hover:border-cc-border-strong hover:text-cc-ink"
        >
            <FileText className="size-3.5 shrink-0" />
            <span className="truncate">
                {[
                    title,
                    versionNumber ? `v${versionNumber}` : null,
                    lastHeading ? `§ ${lastHeading}` : null,
                    pageFrom ? t('p. :page', { page: pageFrom }) : null,
                ]
                    .filter(Boolean)
                    .join(' · ')}
            </span>
        </Link>
    );
}

/**
 * A mail as a source; links to its thread when the id is known.
 */
export function EmailSourceChip({
    subject,
    emailId,
}: {
    subject: string;
    emailId?: number | null;
}) {
    const className =
        'inline-flex max-w-full items-center gap-1.5 rounded-md border border-cc-border bg-cc-panel px-2 py-1 text-[12px] font-medium text-cc-muted';
    const content = (
        <>
            <Mail className="size-3.5 shrink-0" />
            <span className="truncate">{subject}</span>
        </>
    );

    if (emailId === undefined || emailId === null) {
        return <span className={className}>{content}</span>;
    }

    return (
        <Link
            href={emailRoutes.show(emailId)}
            className={`${className} transition-colors hover:border-cc-border-strong hover:text-cc-ink`}
        >
            {content}
        </Link>
    );
}
