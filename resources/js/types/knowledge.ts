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

export type SectionChange = 'unchanged' | 'changed' | 'added';

export type SectionNode = {
    id: number;
    parentId: number | null;
    level: number;
    heading: string | null;
    pageFrom: number | null;
    pageTo: number | null;
    change: SectionChange;
    tokenCount: number;
    chunksCount: number;
    factsCount: number;
};

export type ChunkView = {
    id: number;
    kind: 'text' | 'table' | 'list';
    content: string;
    context: string | null;
    pageFrom: number | null;
    pageTo: number | null;
    tokenCount: number;
    embedded: boolean;
    isCurrent: boolean;
};

export type FactStatus = 'core' | 'supplementary' | 'expired';

export type FactView = {
    id: number;
    statement: string;
    status: FactStatus;
    validFrom: string | null;
    validUntil: string | null;
    pageFrom: number | null;
};

export type SectionDetail = {
    id: number;
    heading: string | null;
    headingPath: string;
    pageFrom: number | null;
    pageTo: number | null;
    change: SectionChange;
    summary: string | null;
    chunks: ChunkView[];
    facts: FactView[];
    topics: { id: number; name: string; origin: 'ai' | 'manual' }[];
};

export type ProcessingStepView = {
    step: string;
    status: 'running' | 'succeeded' | 'failed' | 'skipped';
    attempts: number;
    startedAt: string | null;
    finishedAt: string | null;
    error: string | null;
    meta: Record<string, unknown>;
    usage: {
        calls: number;
        inputTokens: number;
        cachedInputTokens: number;
        outputTokens: number;
        costMicros: number;
    } | null;
};

export type VersionOption = {
    id: number;
    number: number;
    filename: string;
    status: ProcessingStatus;
    createdAt: string | null;
    isPdf: boolean;
};

export type TopicFact = {
    id: number;
    statement: string;
    status: FactStatus;
    validFrom: string | null;
    validUntil: string | null;
    documentId: number | null;
    documentTitle: string | null;
    versionNumber: number | null;
    sectionId: number | null;
    headingPath: string | null;
    pageFrom: number | null;
};

export type TopicSource = {
    linkId: number;
    origin: 'ai' | 'manual';
    via: string | null;
    sectionId: number;
    documentId: number;
    documentTitle: string;
    versionNumber: number;
    headingPath: string;
    pageFrom: number | null;
    summary: string | null;
};

export type FolderOption = {
    id: number;
    name: string;
    depth: number;
    path: string;
};

export type TopicDetail = TopicFolder & {
    description: string | null;
    summary: string | null;
    summaryStale: boolean;
    origin: 'ai' | 'manual';
    depth: number;
    parentId: number | null;
};

export type TopicTreeNode = TopicFolder & {
    parentId: number | null;
    depth: number;
    description: string | null;
    origin: 'ai' | 'manual';
};
