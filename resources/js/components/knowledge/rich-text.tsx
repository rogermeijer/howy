import { cn } from '@/lib/utils';

/**
 * Renders a markdown table (as the extractor writes them) as a real table.
 */
export function MarkdownTable({ markdown }: { markdown: string }) {
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
