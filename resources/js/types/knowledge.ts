export type ProcessingStatus =
    | 'queued'
    | 'extracting'
    | 'structuring'
    | 'contextualizing'
    | 'embedding'
    | 'searchable'
    | 'enriching'
    | 'ready'
    | 'failed';

export type DocumentType =
    | 'handbook'
    | 'policy'
    | 'manual'
    | 'documentation'
    | 'other';

export type DocumentVersionSummary = {
    id: number;
    number: number;
    filename: string;
    mimeType: string;
    sizeBytes: number;
    pageCount: number | null;
    status: ProcessingStatus;
    error: string | null;
    createdAt: string | null;
};

export type KnowledgeDocument = {
    id: number;
    title: string;
    type: DocumentType;
    isCore: boolean;
    language: string;
    effectiveDate: string | null;
    updatedAt: string | null;
    uploadedBy: string | null;
    version: DocumentVersionSummary | null;
};

export type TopicKind = 'theme' | 'project' | 'party';

export type TopicFolder = {
    id: number;
    name: string;
    kind: TopicKind;
    isNew: boolean;
    updatedAt: string | null;
    sourcesCount?: number;
};

export type Option = { value: string; label: string };
