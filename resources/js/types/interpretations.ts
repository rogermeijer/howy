export type InterpretationStatus =
    | 'queued'
    | 'processing'
    | 'done'
    | 'skipped'
    | 'failed';

export type EmailIntent = 'question' | 'information' | 'other';

export type InterpretationOutcome =
    | 'answered'
    | 'not_found'
    | 'suggested'
    | 'unsure'
    | 'added'
    | 'duplicate'
    | 'conflict'
    | 'no_action';

export type InterpretationMode = 'addressed' | 'copied';

export type ReplyStatus = 'none' | 'sent' | 'blocked' | 'failed';

export type InterpretationBrief = {
    status: InterpretationStatus;
    statusLabel: string;
    intent: EmailIntent | null;
    intentLabel: string | null;
    outcome: InterpretationOutcome | null;
    outcomeLabel: string | null;
    /** Something it would add to the knowledge base waits for review. */
    needsReview: boolean;
};

export type InterpretationSource = {
    type: 'document' | 'email';
    id: number;
    title: string;
    sectionId: number | null;
    headingPath: string | null;
    page: number | null;
};

export type InterpretedStatement = {
    statement: string;
    verdict: 'new' | 'duplicate' | 'conflict';
    factId: number | null;
    existingStatement: string | null;
    existingSource: InterpretationSource | null;
    explanation: string | null;
    /** Why someone should look before it goes in, or null. */
    flag: string | null;
    review: 'pending' | 'approved' | 'rejected' | null;
    reviewedBy: string | null;
    reviewedAt: string | null;
};

export type Interpretation = InterpretationBrief & {
    mode: InterpretationMode;
    modeLabel: string;
    confidence: number | null;
    answerConfidence: number | null;
    answerGaps: string | null;
    summary: string | null;
    question: string | null;
    answer: string | null;
    citations: InterpretationSource[];
    statements: InterpretedStatement[];
    replyStatus: ReplyStatus;
    replyStatusLabel: string;
    replyText: string | null;
    replyRecipients: string[];
    repliedAt: string | null;
    error: string | null;
    processedAt: string | null;
};
