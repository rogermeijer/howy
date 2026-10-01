import type { DocumentType, ProcessingStatus } from '@/types';

/**
 * English source strings, translated at render time with t().
 */
export const documentTypeLabels: Record<DocumentType, string> = {
    handbook: 'Handbook',
    policy: 'Policy',
    manual: 'Manual',
    documentation: 'Documentation',
    other: 'Other',
};

export const statusLabels: Record<ProcessingStatus, string> = {
    queued: 'Queued',
    extracting: 'Reading text',
    structuring: 'Finding structure',
    contextualizing: 'Adding context',
    embedding: 'Indexing',
    searchable: 'Searchable',
    enriching: 'Summarising',
    ready: 'Ready',
    failed: 'Failed',
};

/** The pipeline in order; the progress bar is drawn from this. */
export const pipeline: ProcessingStatus[] = [
    'queued',
    'extracting',
    'structuring',
    'contextualizing',
    'embedding',
    'searchable',
    'enriching',
    'ready',
];

export function isProcessing(status: ProcessingStatus | undefined): boolean {
    return status !== undefined && status !== 'ready' && status !== 'failed';
}

export function formatBytes(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function sourcesLabel(
    t: (key: string, replacements?: Record<string, string | number>) => string,
    count: number,
): string {
    return count === 1 ? t('1 source') : t(':count sources', { count });
}
