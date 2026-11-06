import { cn } from '@/lib/utils';

/**
 * Renders a markdown table (as the extractor writes them) as a real table.
 * Compact, it keeps cells on one line and scrolls sideways in a narrow
 * column; `marked` rows (body rows, from 0) stand out.
 */
export function MarkdownTable({
    markdown,
    compact = false,
    marked = [],
}: {
    markdown: string;
    compact?: boolean;
    marked?: number[];
}) {
    const rows = markdown
        .split('\n')
        .filter((line) => !/^\|(\s*:?-+:?\s*\|)+$/.test(line.trim()))
        .map((line) =>
            line
                .trim()
                .replace(/^\|\s?|\s?\|$/g, '')
                .split(/\s?(?<!\\)\|\s?/)
                .map((cell) => cell.replace(/\\\|/g, '|')),
        );
    const [head, ...body] = rows;

    return (
        <div className="overflow-x-auto rounded-lg border border-cc-border">
            <table
                className={cn(
                    'text-left',
                    compact
                        ? 'w-max min-w-full text-[12px] whitespace-nowrap'
                        : 'w-full text-[13px]',
                )}
            >
                <thead className="bg-cc-bg">
                    <tr>
                        {head?.map((cell, index) => (
                            <th
                                key={index}
                                className={cn(
                                    'font-semibold',
                                    compact ? 'px-2.5 py-1.5' : 'px-3 py-2',
                                )}
                            >
                                {cell}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {body.map((row, rowIndex) => (
                        <tr
                            key={rowIndex}
                            className={cn(
                                'border-t border-cc-border',
                                marked.includes(rowIndex) &&
                                    'bg-cc-highlight/60 font-semibold text-cc-ink',
                            )}
                        >
                            {row.map((cell, index) => (
                                <td
                                    key={index}
                                    className={cn(
                                        'align-top',
                                        compact ? 'px-2.5 py-1.5' : 'px-3 py-2',
                                    )}
                                >
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

/**
 * Knowledge text: paragraphs separated by blank lines, tables in pipe syntax.
 */
export function RichText({
    text,
    className,
}: {
    text: string;
    className?: string;
}) {
    const blocks = text.split(/\n{2,}/);

    return (
        <div className={cn('flex flex-col gap-3', className)}>
            {blocks.map((block, index) =>
                block.trim().startsWith('|') ? (
                    <MarkdownTable key={index} markdown={block} />
                ) : (
                    <p
                        key={index}
                        className="cc-body whitespace-pre-wrap text-cc-ink"
                    >
                        {block}
                    </p>
                ),
            )}
        </div>
    );
}
