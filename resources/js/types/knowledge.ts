import type { InterpretedStatement } from './interpretations';

export type ProcessingStatus =
    | 'queued'
    | 'extracting'
    | 'structuring'
    | 'contextualizing'
    | 'embedding'
    | 'searchable'
    | 'enriching'
    | 'review'
    | 'ready'
    | 'failed'
    | 'rejected';

export type DocumentType =
    | 'handbook'
    | 'policy'
    | 'manual'
    | 'documentation'
    | 'correspondence'
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
    isApproved: boolean;
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
    /** The version that answers; null while the first one waits for approval. */
    publishedVersion: { id: number; number: number } | null;
    /** The newest version, published or not. */
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

export type FactStatus = 'core' | 'supplementary' | 'expired' | 'proposed';

/** One place a fact is stated. */
export type FactSourceView = {
    kind: 'document' | 'mail';
    title: string;
    documentId: number | null;
    versionNumber: number | null;
    sectionId: number | null;
    headingPath: string | null;
    pageFrom: number | null;
    emailId: number | null;
    /** Its document was removed and the fact kept. */
    removed: boolean;
};

export type FactView = {
    id: number;
    statement: string;
    status: FactStatus;
    validFrom: string | null;
    validUntil: string | null;
    pageFrom: number | null;
    /** Every place it is stated, the cited one first. */
    sources: FactSourceView[];
    /** Waiting for approval: how it compares with what is known. */
    verdict: 'new' | 'duplicate' | 'conflict' | 'unchecked' | null;
    /** The known fact it repeats or contradicts. */
    known: NonNullable<JudgedFact['existing']> | null;
    /** Why it contradicts the known fact. */
    explanation: string | null;
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
};

export type VersionOption = {
    id: number;
    number: number;
    filename: string;
    status: ProcessingStatus;
    isApproved: boolean;
    isCurrent: boolean;
    createdAt: string | null;
    isPdf: boolean;
};

/** What approving a version would change, for the administrator who decides. */
export type DocumentSourceView = {
    kind: 'upload' | 'mail';
    filename: string;
    user: string | null;
    emailId: number | null;
    subject: string | null;
    from: string | null;
    context: string | null;
    createdAt: string | null;
};

/** A fact of a version waiting for approval, with where it is in the document. */
export type ReviewFact = {
    id: number;
    statement: string;
    sectionId: number | null;
    heading: string | null;
};

export type VersionReview = {
    replaces: number | null;
    /** No earlier version to compare with. */
    firstVersion: boolean;
    uploadedBy: string | null;
    sources: DocumentSourceView[];
    summary: string | null;
    error: string | null;
    sections: {
        unchanged: number;
        changed: number;
        added: number;
        removed: number;
    };
    placement: {
        kind: 'new' | 'version' | 'similar' | 'same_content';
        document_id?: number | null;
        version_number?: number | null;
        score?: number | null;
        reason?: string | null;
        title?: string | null;
    } | null;
    facts: {
        new: number;
        duplicate: number;
        conflict: number;
        unchecked: number;
    };
    conflicts: JudgedFact[];
    duplicates: JudgedFact[];
    newFacts: ReviewFact[];
    uncheckedFacts: ReviewFact[];
    folders: { id: number; name: string; isNew: boolean }[];
};

/** A processed document version in the approvals list. */
export type ApprovalDocument = {
    documentId: number;
    versionId: number;
    title: string;
    filename: string;
    versionNumber: number;
    /** The live version it would replace, or null for a new document. */
    replaces: number | null;
    /** An upload that looks like a document already there. */
    hint: {
        kind: 'similar' | 'same_content';
        title: string | null;
    } | null;
    sources: Pick<
        DocumentSourceView,
        'kind' | 'user' | 'emailId' | 'subject' | 'from'
    >[];
    error: string | null;
    processedAt: string | null;
};

/** A mail with the statements it would add or change, in the approvals list. */
export type ApprovalMail = {
    emailId: number;
    subject: string | null;
    from: string | null;
    receivedAt: string | null;
    summary: string | null;
    statements: InterpretedStatement[];
};

/** A new fact with the known one it duplicates or contradicts. */
export type JudgedFact = {
    id: number;
    statement: string;
    explanation: string | null;
    existing: {
        statement: string;
        status: FactStatus;
        source:
            | { type: 'email'; id: number; title: string }
            | {
                  type: 'document';
                  id: number | null;
                  title: string;
                  versionNumber: number | null;
              };
    } | null;
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
    /** Every place it is stated, the cited one first. */
    sources: FactSourceView[];
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
