import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, Folder, Paperclip } from 'lucide-react';
import { Tag } from '@/components/cc/tag';
import { inbox, knowledge } from '@/routes';
import { useTranslations } from '@/hooks/use-translations';

const extracted = [
    { label: 'Project', value: 'Noordkade · fase 2' },
    { label: 'Oude datum', value: '14 oktober 2026', struck: true },
    { label: 'Nieuwe datum', value: '28 oktober 2026', strong: true },
    { label: 'Deadline', value: 'Bevestigen vóór vr 26 sep' },
];

const threadHistory = [
    {
        dot: 'bg-cc-decision-fg',
        text: 'Besluit · Fase 2 oplevering 14 oktober',
        date: '2 sep',
    },
    {
        dot: 'bg-cc-question-fg',
        text: 'Vraag · Levertijd kozijnen bevestigd?',
        date: '28 aug',
    },
];

export default function EmailShow() {
    const t = useTranslations();
    return (
        <>
            <Head title="RE: Planning fase 2" />

            <div className="flex flex-col gap-5">
                <div className="cc-caption flex items-center gap-2">
                    <Link
                        href={inbox()}
                        className="inline-flex items-center gap-1.5 text-cc-subtle transition-colors hover:text-cc-ink"
                    >
                        <ChevronLeft className="size-3.5" />
                        {t('Inbox')}
                    </Link>
                    <span>/</span>
                    <span className="font-medium text-cc-ink">
                        RE: Planning fase 2 – opleverdatum
                    </span>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_440px]">
                    <article className="cc-panel flex flex-col gap-6 p-8">
                        <div className="flex flex-col gap-3.5">
                            <h1 className="cc-subtitle text-[24px]">
                                RE: Planning fase 2 – opleverdatum
                            </h1>
                            <div className="flex items-center gap-3.5">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-cc-raised text-[13px] font-semibold text-cc-ink">
                                    SV
                                </div>
                                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <div className="truncate text-[15px] font-semibold">
                                        Sanne de Vries{' '}
                                        <span className="font-normal text-cc-subtle">
                                            &lt;sanne@noordkade.nl&gt;
                                        </span>
                                    </div>
                                    <div className="cc-caption truncate">
                                        aan inbox@cc.nl, roger@cc.nl ·
                                        Bouwbedrijf Noordkade
                                    </div>
                                </div>
                                <div className="cc-caption shrink-0">
                                    Vandaag 09:41
                                </div>
                            </div>
                        </div>

                        <div className="h-px bg-cc-border" />

                        <div className="flex flex-col gap-3.5 text-[15px] leading-[1.65]">
                            <p>Hoi Roger,</p>
                            <p>
                                Zoals besproken in het bouwoverleg van dinsdag:
                                de levering van de kozijnen loopt twee weken
                                uit. Daarmee{' '}
                                <mark className="rounded bg-cc-decision-bg px-1 py-px text-cc-ink">
                                    verschuift de oplevering van fase 2 van 14
                                    oktober naar 28 oktober
                                </mark>
                                {'. '}
                                Fase 3 blijft ongewijzigd staan op 20 november.
                            </p>
                            <p>
                                <mark className="rounded bg-cc-action-bg px-1 py-px text-cc-ink">
                                    Kun je dit vóór vrijdag bevestigen
                                </mark>
                                {', '}
                                dan pas ik de planning aan en informeer ik de
                                onderaannemers?
                            </p>
                            <p>
                                Groet,
                                <br />
                                Sanne
                            </p>
                        </div>

                        <div className="h-px bg-cc-border" />

                        <div className="cc-caption flex items-center gap-2.5">
                            <Paperclip className="size-4 shrink-0" />1 bijlage ·{' '}
                            <Link
                                href={knowledge()}
                                className="font-medium text-cc-ink underline underline-offset-[3px]"
                            >
                                Planning-fase2-v4.xlsx
                            </Link>{' '}
                            · 84 KB
                        </div>
                    </article>

                    <aside className="flex flex-col gap-4">
                        <div className="cc-panel-dark flex flex-col gap-5 p-7">
                            <div className="flex items-center justify-between">
                                <div className="cc-label text-cc-faint">
                                    {t('Interpretation')}
                                </div>
                                <div className="text-[12px] text-cc-dark-text">
                                    {t('Confidence')}{' '}
                                    <span className="font-semibold text-cc-bg">
                                        92%
                                    </span>
                                </div>
                            </div>

                            <div className="flex flex-col gap-2.5">
                                <Tag kind="decision" className="self-start" />
                                <div className="text-[17px] leading-[1.35] font-semibold tracking-[-0.01em]">
                                    Oplevering fase 2 verschuift naar 28 oktober
                                </div>
                                <div className="text-[14px] leading-[1.55] text-cc-dark-text">
                                    Oorzaak: kozijnen twee weken vertraagd. Fase
                                    3 blijft 20 november. Sanne vraagt
                                    bevestiging vóór vrijdag 26 september.
                                </div>
                            </div>

                            <div className="flex flex-col gap-2.5">
                                <div className="cc-label text-cc-faint">
                                    {t('Extracted from the email')}
                                </div>
                                <dl className="flex flex-col gap-2">
                                    {extracted.map((row) => (
                                        <div
                                            key={row.label}
                                            className="grid grid-cols-[110px_minmax(0,1fr)] gap-3 text-[14px]"
                                        >
                                            <dt className="text-cc-faint">
                                                {row.label}
                                            </dt>
                                            <dd
                                                className={[
                                                    'text-cc-bg m-0',
                                                    row.struck &&
                                                        'decoration-cc-faint line-through',
                                                    row.strong &&
                                                        'font-semibold',
                                                ]
                                                    .filter(Boolean)
                                                    .join(' ')}
                                            >
                                                {row.value}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>

                            <div className="flex flex-col gap-2.5">
                                <div className="cc-label text-cc-faint">
                                    {t('Will be saved in')}
                                </div>
                                <Link
                                    href={knowledge()}
                                    className="flex items-center gap-2.5 rounded-[10px] border-[1.5px] border-cc-dark-border px-3.5 py-3 text-[14px] transition-colors hover:border-cc-faint"
                                >
                                    <Folder className="size-[18px] shrink-0 text-cc-dark-text" />
                                    <span className="min-w-0 flex-1 truncate">
                                        Projecten › Noordkade ›{' '}
                                        <span className="font-semibold">
                                            Planning.md
                                        </span>
                                    </span>
                                    <span className="shrink-0 text-[12px] text-cc-faint">
                                        wijzigen
                                    </span>
                                </Link>
                            </div>

                            <div className="flex flex-col gap-2.5 pt-1">
                                <button
                                    type="button"
                                    className="h-12 cursor-pointer rounded-[10px] bg-cc-accent text-[15px] font-semibold text-cc-ink transition-opacity hover:opacity-90"
                                >
                                    {t('Confirm and save')}
                                </button>
                                <div className="grid grid-cols-2 gap-2.5">
                                    <button
                                        type="button"
                                        className="h-11 cursor-pointer rounded-[10px] border-[1.5px] border-cc-dark-border text-[14px] font-semibold text-cc-bg transition-colors hover:border-cc-bg"
                                    >
                                        {t('Edit')}
                                    </button>
                                    <button
                                        type="button"
                                        className="h-11 cursor-pointer rounded-[10px] text-[14px] font-semibold text-cc-dark-text transition-colors hover:text-cc-bg"
                                    >
                                        {t('Ignore')}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div className="cc-panel flex flex-col gap-3 p-6">
                            <div className="cc-label">
                                {t('Earlier in this thread')}
                            </div>
                            {threadHistory.map((item) => (
                                <div
                                    key={item.text}
                                    className="flex items-center gap-2.5 text-[14px]"
                                >
                                    <span
                                        className={`size-1.5 shrink-0 rounded-full ${item.dot}`}
                                    />
                                    <span className="min-w-0 flex-1 truncate">
                                        {item.text}
                                    </span>
                                    <span className="cc-caption shrink-0">
                                        {item.date}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}
