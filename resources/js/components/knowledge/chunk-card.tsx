import { Sparkles } from 'lucide-react';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { ChunkView } from '@/types';

/**
 * Renders a markdown table (as the extractor writes them) as a real table.
 */
function MarkdownTable({ markdown }: { markdown: string }) {
    const rows = markdown
        .split('\n')
        .filter((line) => !/^\|(\s*-+\s*\|)+$/.test(line.replace(/ /g, '')))
        .map((line) =>
            line
                .replace(/^\|\s?|\s?\|$/g, '')
                .split(/\s?(?<!\\)\|\s?/)
                .map((cell) => cell.replace(/\\\|/g, '|')),
        );
    const [head, ...body] = rows;

    return (
        <div className="overflow-x-auto rounded-lg border border-cc-border">
            <table className="w-full text-left text-[13px]">
                <thead className="bg-cc-bg">
                    <tr>
                        {head?.map((cell, index) => (
                            <th key={index} className="px-3 py-2 font-semibold">
                                {cell}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {body.map((row, rowIndex) => (
                        <tr
                            key={rowIndex}
                            className="border-t border-cc-border"
                        >
                            {row.map((cell, index) => (
                                <td key={index} className="px-3 py-2 align-top">
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

const kindLabels = { text: 'Text', table: 'Table', list: 'List' } as const;

export function ChunkCard({
    chunk,
    index,
}: {
    chunk: ChunkView;
    index: number;
}) {
    const t = useTranslations();

    return (
        <article className="flex flex-col gap-3 rounded-xl border border-cc-border bg-cc-panel p-4">
            <header className="flex flex-wrap items-center gap-2 text-[12px]">
                <span className="font-semibold text-cc-ink">
                    {t('Chunk :number', { number: index + 1 })}
                </span>
                <span className="rounded-md bg-cc-raised px-1.5 py-0.5 font-semibold text-cc-muted">
                    {t(kindLabels[chunk.kind])}
                </span>
                {chunk.pageFrom !== null && (
                    <span className="text-cc-subtle">
                        {chunk.pageFrom === chunk.pageTo
                            ? t('p. :page', { page: chunk.pageFrom })
                            : t('p. :from–:to', {
                                  from: chunk.pageFrom,
                                  to: chunk.pageTo ?? chunk.pageFrom,
                              })}
                    </span>
                )}
                <span className="text-cc-subtle">
                    {t('~:count tokens', { count: chunk.tokenCount })}
                </span>
                <span
                    className={cn(
                        'ml-auto rounded-full px-2 py-0.5 font-semibold',
                        chunk.embedded
                            ? 'bg-cc-decision-bg text-cc-decision-fg'
                            : 'bg-cc-pending-bg text-cc-pending-fg',
                    )}
                >
                    {chunk.embedded ? t('Indexed') : t('Not indexed yet')}
                </span>
            </header>

            {chunk.context && (
                <p className="flex gap-2 rounded-lg bg-cc-knowledge-bg/60 px-3 py-2 text-[13px] leading-relaxed text-cc-knowledge-fg">
                    <Sparkles className="mt-0.5 size-3.5 shrink-0" />
                    <span>{chunk.context}</span>
                </p>
            )}

            {chunk.kind === 'table' ? (
                <MarkdownTable markdown={chunk.content} />
            ) : (
                <p className="cc-body whitespace-pre-wrap text-cc-ink">
                    {chunk.content}
                </p>
            )}
        </article>
    );
}
