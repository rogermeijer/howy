import { Head, Link } from '@inertiajs/react';
import { Copy, MessagesSquare } from 'lucide-react';
import { useState } from 'react';
import { Confidence } from '@/components/cc/confidence';
import { PageHeader } from '@/components/cc/page-header';
import { type InterpretationKind, Tag } from '@/components/cc/tag';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { show } from '@/routes/emails';
import { useTranslations } from '@/hooks/use-translations';

type Email = {
    id: string;
    sender: string;
    org: string;
    subject: string;
    interpretation: string;
    kind: InterpretationKind;
    tagLabel?: string;
    confidence: number;
    time: string;
    unread: boolean;
};

const emails: Email[] = [
    {
        id: 'planning-fase-2',
        sender: 'Sanne de Vries',
        org: 'Bouwbedrijf Noordkade',
        subject: 'RE: Planning fase 2 – opleverdatum',
        interpretation:
            'Oplevering fase 2 verschuift van 14 naar 28 oktober; Sanne wil bevestiging vóór vrijdag.',
        kind: 'decision',
        confidence: 92,
        time: '09:41',
        unread: true,
    },
    {
        id: 'vergunning-terras',
        sender: 'Tom Bakker',
        org: 'Gemeente Haarlem',
        subject: 'Vergunning terras – aanvullende stukken',
        interpretation:
            'Twee documenten aanleveren vóór 3 oktober: situatietekening en constructieberekening.',
        kind: 'action',
        confidence: 88,
        time: '09:12',
        unread: true,
    },
    {
        id: 'btw-onderaannemers',
        sender: 'Fatima el Amrani',
        org: 'Intern · Finance',
        subject: 'Welke btw-code gebruiken we voor onderaannemers?',
        interpretation:
            'Vraag over verlegde btw; mogelijk antwoord in kennisbank "Facturatie › Onderaanneming".',
        kind: 'pending',
        tagLabel: 'Vraag · bevestigen',
        confidence: 61,
        time: '08:55',
        unread: true,
    },
    {
        id: 'warmtepomp-v3',
        sender: 'Jeroen Visser',
        org: 'Visser Installatietechniek',
        subject: 'Werkwijze warmtepomp-aansluiting (v3)',
        interpretation:
            'Nieuwe procedure opgeslagen als "Installatie › Warmtepomp › Werkwijze v3.pdf".',
        kind: 'knowledge',
        confidence: 96,
        time: 'Gisteren',
        unread: false,
    },
    {
        id: 'kleurstaal-gevel',
        sender: 'Lotte Hendriks',
        org: 'Studio Hendriks',
        subject: 'Kleurstaal gevel – akkoord?',
        interpretation:
            'Vraagt akkoord op RAL 7016 voor de gevelbeplating; foto van staal bijgevoegd.',
        kind: 'question',
        confidence: 84,
        time: 'Gisteren',
        unread: false,
    },
    {
        id: 'nieuwsbrief-bouwend',
        sender: 'Nieuwsbrief Bouwend NL',
        org: 'noreply@bouwendnederland.nl',
        subject: 'Weekoverzicht: cao-nieuws en events',
        interpretation: 'Geen actie of kennis gevonden; genegeerd.',
        kind: 'noise',
        confidence: 97,
        time: 'Ma',
        unread: false,
    },
    {
        id: 'geen-weekendwerk',
        sender: 'Mark Jansen',
        org: 'Intern · Directie',
        subject: 'Besluit: geen weekendwerk in Q4',
        interpretation:
            'Toegevoegd aan "Beleid › Werktijden"; geldt vanaf 1 oktober voor alle projecten.',
        kind: 'decision',
        confidence: 95,
        time: 'Ma',
        unread: false,
    },
];

const filters = [
    { id: 'all', label: 'All' },
    { id: 'pending', label: 'To confirm · 3' },
    { id: 'question', label: 'Question' },
    { id: 'decision', label: 'Decision' },
    { id: 'action', label: 'Action' },
    { id: 'knowledge', label: 'Knowledge' },
];

const headerGrid =
    'hidden lg:grid grid-cols-[24px_220px_minmax(0,1fr)_170px_150px_72px] items-center gap-5';

export default function Inbox() {
    const [filter, setFilter] = useState('all');
    const t = useTranslations();

    const visible =
        filter === 'all'
            ? emails
            : emails.filter((email) =>
                  filter === 'pending'
                      ? email.kind === 'pending'
                      : email.kind === filter,
              );

    return (
        <>
            <Head title={t('Inbox')} />

            <div className="flex flex-col gap-6">
                <PageHeader
                    title={t('Inbox')}
                    description={t(
                        '12 emails processed today · 3 waiting for your confirmation',
                    )}
                    actions={
                        <>
                            <Button variant="outline" className="h-10">
                                <MessagesSquare />
                                {t('Threads')}
                            </Button>
                            <Button className="h-10">
                                {t('Copy address')}
                                <span className="font-mono font-medium text-cc-dark-text">
                                    inbox@cc.nl
                                </span>
                                <Copy />
                            </Button>
                        </>
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    {filters.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => setFilter(item.id)}
                            className={cn(
                                'cc-pill',
                                filter === item.id && 'cc-pill-active',
                            )}
                        >
                            {t(item.label)}
                        </button>
                    ))}
                    <div className="flex-1" />
                    <span className="cc-caption">{t('Sorted by newest')}</span>
                </div>

                <div className="cc-panel overflow-hidden">
                    <div
                        className={cn(
                            headerGrid,
                            'cc-label border-b border-cc-border px-6 py-2.5',
                        )}
                    >
                        <div />
                        <div>{t('Sender')}</div>
                        <div>{t('Subject & interpretation')}</div>
                        <div>{t('Type')}</div>
                        <div>{t('Confidence')}</div>
                        <div className="text-right">{t('Time')}</div>
                    </div>

                    {visible.map((email) => (
                        <Link
                            key={email.id}
                            href={show(email.id)}
                            className={cn(
                                'cc-row-inbox border-b border-cc-border px-6 py-4 transition-colors last:border-b-0 hover:bg-cc-bg',
                                email.unread && 'bg-[#fffdfa]',
                            )}
                        >
                            <div className="flex justify-center [grid-area:dot]">
                                {email.unread && (
                                    <span className="size-2 rounded-full bg-cc-accent" />
                                )}
                            </div>

                            <div className="flex min-w-0 flex-col gap-0.5 [grid-area:sender]">
                                <div
                                    className={cn(
                                        'truncate text-[15px]',
                                        email.unread
                                            ? 'font-semibold'
                                            : 'font-medium',
                                    )}
                                >
                                    {email.sender}
                                </div>
                                <div className="cc-caption truncate">
                                    {email.org}
                                </div>
                            </div>

                            <div className="flex min-w-0 flex-col gap-1 [grid-area:subject]">
                                <div
                                    className={cn(
                                        'text-[15px] lg:truncate',
                                        email.unread
                                            ? 'font-semibold'
                                            : 'font-medium',
                                    )}
                                >
                                    {email.subject}
                                </div>
                                <div className="text-[14px] text-cc-muted lg:truncate">
                                    {email.interpretation}
                                </div>
                            </div>

                            <div className="[grid-area:tag]">
                                <Tag kind={email.kind} label={email.tagLabel} />
                            </div>

                            <Confidence
                                value={email.confidence}
                                className="hidden lg:flex lg:[grid-area:confidence]"
                            />

                            <div className="cc-caption text-right [grid-area:time]">
                                {email.time}
                            </div>
                        </Link>
                    ))}

                    {visible.length === 0 && (
                        <div className="px-6 py-16 text-center text-[14px] text-cc-subtle">
                            Geen e-mails met dit label.
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
