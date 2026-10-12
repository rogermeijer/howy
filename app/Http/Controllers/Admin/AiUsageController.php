<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AiUsageIndexRequest;
use App\Services\Admin\AiUsageFilters;
use App\Services\Admin\AiUsageLog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * How the platform uses AI: cost and tokens per account, step, model, agent,
 * thread and message, and every call with what was sent. Reads through
 * AiUsageLog, the sanctioned cross-account path.
 */
class AiUsageController extends Controller
{
    public function index(AiUsageIndexRequest $request, AiUsageLog $log): Response
    {
        $filters = AiUsageFilters::fromInput($request->validated());

        return Inertia::render('admin/ai/index', [
            'filters' => $filters->toQuery(),
            'options' => $log->options(),
            'totals' => $log->totals($filters),
            'daily' => $log->daily($filters),
            'breakdowns' => [
                'account' => $log->breakdown($filters, 'account'),
                'step' => $log->breakdown($filters, 'step'),
                'model' => $log->breakdown($filters, 'model'),
                'agent' => $log->breakdown($filters, 'agent'),
                'thread' => $log->breakdown($filters, 'thread'),
                'subject' => $log->breakdown($filters, 'subject'),
            ],
            'calls' => $log->calls($filters),
            'retentionDays' => (int) config('knowledge.ai_log.payload_retention_days'),
        ]);
    }

    public function show(int $call, AiUsageLog $log): Response
    {
        $record = $log->find($call) ?? abort(404);

        return Inertia::render('admin/ai/show', [
            'call' => $record,
            'retentionDays' => (int) config('knowledge.ai_log.payload_retention_days'),
        ]);
    }
}
