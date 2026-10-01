import { Head, Link } from '@inertiajs/react';
import {
    FileText,
    Folder,
    LayoutGrid,
    List,
    MoreHorizontal,
    Plus,
} from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/cc/page-header';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { inbox, knowledge } from '@/routes';
import { useTranslations } from '@/hooks/use-translations';

const folders = [
    { name: 'Noordkade', meta: '5 bestanden · vandaag', tinted: true },
    { name: 'Terras Haarlem', meta: '3 bestanden · gisteren', tinted: false },
    {
        name: 'Studio Hendriks',
        meta: '2 bestanden · 3 dagen geleden',
        tinted: false,
    },
    { name: 'Afgerond 2025', meta: '2 bestanden · juli', tinted: false },
];

type File = {
    name: string;
    description: string;
    thread: string;
    threadDot: string;
    type: string;
    updated: string;
    // Demo data: emails are real now, but these files are not, so their
    // source links go to the inbox until the knowledge base is built.
    emailId: string;
};

const files: File[] = [
    {
        name: 'Planning.md',
        description: 'Noordkade · oplevering fase 2 verschoven naar 28 oktober',
        thread: 'RE: Planning fase 2 – Sanne de Vries',
        threadDot: 'bg-cc-decision-fg',
        type: 'Besluit',
        updated: 'Vandaag 09:41',
        emailId: 'planning-fase-2',
    },
    {
        name: 'Planning-fase2-v4.xlsx',
        description: 'Bijlage · 84 KB',
        thread: 'RE: Planning fase 2 – Sanne de Vries',
        threadDot: 'bg-cc-decision-fg',
        type: 'Bijlage',
        updated: 'Vandaag 09:41',
        emailId: 'planning-fase-2',
    },
    {
        name: 'Vergunning – openstaande stukken.md',
        description:
            'Terras Haarlem · situatietekening en constructieberekening vóór 3 oktober',
        thread: 'Vergunning terras – Tom Bakker',
        threadDot: 'bg-cc-action-fg',
        type: 'Actie',
        updated: 'Vandaag 09:12',
        emailId: 'vergunning-terras',
    },
    {
        name: 'Werkwijze warmtepomp v3.pdf',
        description: 'Installatie · procedure aansluiting, vervangt v2',
        thread: 'Werkwijze warmtepomp – Jeroen Visser',
        threadDot: 'bg-cc-knowledge-fg',
        type: 'Kennis',
        updated: 'Gisteren',
        emailId: 'warmtepomp-v3',
    },
    {
        name: 'Werktijden.md',
        description: 'Beleid · geen weekendwerk in Q4, vanaf 1 oktober',
        thread: 'Besluit: geen weekendwerk – Mark Jansen',
        threadDot: 'bg-cc-decision-fg',
        type: 'Besluit',
        updated: 'Maandag',
        emailId: 'geen-weekendwerk',
    },
];

const headerGrid =
    'hidden lg:grid grid-cols-[28px_minmax(0,1fr)_240px_120px_130px_40px] items-center gap-5';

export default function Knowledge() {
    const [view, setView] = useState<'list' | 'grid'>('list');
    const t = useTranslations();

    return (
        <>
            <Head title={t('Knowledge base')} />

            <div className="flex flex-col gap-7">
                <PageHeader
                    title={t('Projects')}
                    breadcrumb={
                        <div className="cc-caption flex items-center gap-2">
                            <Link
                                href={knowledge()}
                                className="text-cc-subtle transition-colors hover:text-cc-ink"
                            >
                                {t('Knowledge base')}
                            </Link>
                            <span>/</span>
                            <span className="font-medium text-cc-ink">
                                {t('Projects')}
                            </span>
                        </div>
                    }
                    description={t(
                        '4 folders · 12 files · everything comes from email, every file links back to its thread',
                    )}
                    actions={
                        <>
                            <div className="flex overflow-hidden rounded-[10px] border-[1.5px] border-cc-border-strong bg-cc-panel">
                                <button
                                    type="button"
                                    aria-label={t('List view')}
                                    aria-pressed={view === 'list'}
                                    onClick={() => setView('list')}
                                    className={cn(
                                        'flex size-[38px] cursor-pointer items-center justify-center transition-colors',
                                        view === 'list'
                                            ? 'bg-cc-ink text-cc-bg'
                                            : 'text-cc-ink hover:bg-cc-raised',
                                    )}
                                >
                                    <List className="size-4" />
                                </button>
                                <button
                                    type="button"
                                    aria-label={t('Grid view')}
                                    aria-pressed={view === 'grid'}
                                    onClick={() => setView('grid')}
                                    className={cn(
                                        'flex size-[38px] cursor-pointer items-center justify-center transition-colors',
                                        view === 'grid'
                                            ? 'bg-cc-ink text-cc-bg'
                                            : 'text-cc-ink hover:bg-cc-raised',
                                    )}
                                >
                                    <LayoutGrid className="size-4" />
                                </button>
                            </div>
                            <Button variant="outline" className="h-10">
                                Nieuwe map
                            </Button>
                            <Button className="h-10">
                                <Plus />
                                Bestand toevoegen
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {folders.map((folder) => (
                        <Link
                            key={folder.name}
                            href={knowledge()}
                            className="cc-panel flex flex-col gap-3.5 p-5 transition-colors hover:border-cc-border-strong"
                        >
                            <Folder
                                className="size-[26px] text-cc-ink"
                                strokeWidth={1.8}
                                fill={folder.tinted ? '#ffe4ec' : '#ecebe6'}
                            />
                            <div className="flex flex-col gap-0.5">
                                <div className="text-[15px] font-semibold">
                                    {folder.name}
                                </div>
                                <div className="cc-caption">{folder.meta}</div>
                            </div>
                        </Link>
                    ))}
                </div>

                {view === 'list' ? (
                    <div className="cc-panel overflow-hidden">
                        <div
                            className={cn(
                                headerGrid,
                                'cc-label border-b border-cc-border px-6 py-2.5',
                            )}
                        >
                            <div />
                            <div>{t('Name')}</div>
                            <div>{t('Source (thread)')}</div>
                            <div>{t('Type')}</div>
                            <div>{t('Updated')}</div>
                            <div />
                        </div>

                        {files.map((file) => (
                            <Link
                                key={file.name}
                                href={inbox()}
                                className="cc-row-file border-b border-cc-border px-6 py-3.5 transition-colors last:border-b-0 hover:bg-cc-bg"
                            >
                                <FileText
                                    className="size-5 text-cc-ink [grid-area:icon]"
                                    strokeWidth={1.8}
                                />
                                <div className="flex min-w-0 flex-col gap-0.5 [grid-area:name]">
                                    <div className="truncate text-[15px] font-semibold">
                                        {file.name}
                                    </div>
                                    <div className="cc-caption lg:truncate">
                                        {file.description}
                                    </div>
                                </div>
                                <div className="cc-caption flex min-w-0 items-center gap-2 [grid-area:thread]">
                                    <span
                                        className={`size-1.5 shrink-0 rounded-full ${file.threadDot}`}
                                    />
                                    <span className="truncate">
                                        {file.thread}
                                    </span>
                                </div>
                                <div className="[grid-area:type]">
                                    <span className="rounded-md bg-cc-raised px-2 py-1 text-[12px] font-semibold text-cc-muted">
                                        {file.type}
                                    </span>
                                </div>
                                <div className="cc-caption text-right [grid-area:updated] lg:text-left">
                                    {file.updated}
                                </div>
                                <span
                                    aria-hidden
                                    className="hidden size-8 items-center justify-center rounded-lg text-cc-subtle lg:flex lg:[grid-area:actions]"
                                >
                                    <MoreHorizontal className="size-4" />
                                </span>
                            </Link>
                        ))}
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {files.map((file) => (
                            <Link
                                key={file.name}
                                href={inbox()}
                                className="cc-panel flex flex-col gap-3 p-5 transition-colors hover:border-cc-border-strong"
                            >
                                <FileText
                                    className="size-6 text-cc-ink"
                                    strokeWidth={1.8}
                                />
                                <div className="flex flex-col gap-1">
                                    <div className="truncate text-[15px] font-semibold">
                                        {file.name}
                                    </div>
                                    <div className="cc-caption line-clamp-2">
                                        {file.description}
                                    </div>
                                </div>
                                <div className="mt-auto flex items-center gap-2 border-t border-cc-border pt-3">
                                    <span
                                        className={`size-1.5 shrink-0 rounded-full ${file.threadDot}`}
                                    />
                                    <span className="cc-caption truncate">
                                        {file.thread}
                                    </span>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
