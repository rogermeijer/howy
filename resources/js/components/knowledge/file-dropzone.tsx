import { FileUp } from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { formatBytes } from './labels';

type Props = {
    file: File | null;
    onChange: (file: File | null) => void;
    maxMegabytes: number;
    invalid?: boolean;
};

const accept =
    '.pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document';

/**
 * A clearly bounded drop target that is also a button: click to browse, or drop
 * a PDF or Word file on it.
 */
export function FileDropzone({ file, onChange, maxMegabytes, invalid }: Props) {
    const t = useTranslations();
    const input = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);

    return (
        <button
            type="button"
            onClick={() => input.current?.click()}
            onDragOver={(event) => {
                event.preventDefault();
                setDragging(true);
            }}
            onDragLeave={() => setDragging(false)}
            onDrop={(event) => {
                event.preventDefault();
                setDragging(false);
                onChange(event.dataTransfer.files.item(0));
            }}
            className={cn(
                'flex w-full cursor-pointer flex-col items-center gap-2 rounded-xl border-[1.5px] border-dashed bg-cc-bg px-6 py-8 text-center transition-colors',
                dragging
                    ? 'border-cc-accent bg-cc-accent-tint/50'
                    : 'border-cc-border-strong hover:border-cc-ink',
                invalid && 'border-cc-action-fg',
            )}
        >
            <span className="flex size-11 items-center justify-center rounded-xl bg-cc-panel text-cc-ink ring-1 ring-cc-border">
                <FileUp className="size-5" />
            </span>
            {file ? (
                <>
                    <span className="text-[15px] font-semibold break-all">
                        {file.name}
                    </span>
                    <span className="cc-caption">
                        {formatBytes(file.size)} ·{' '}
                        {t('Click to choose another file')}
                    </span>
                </>
            ) : (
                <>
                    <span className="text-[15px] font-semibold">
                        {t('Drop a PDF or Word file here, or click to browse')}
                    </span>
                    <span className="cc-caption">
                        {t('.pdf or .docx, up to :size MB', {
                            size: maxMegabytes,
                        })}
                    </span>
                </>
            )}
            <input
                ref={input}
                type="file"
                accept={accept}
                className="hidden"
                onChange={(event) =>
                    onChange(event.target.files?.item(0) ?? null)
                }
            />
        </button>
    );
}
