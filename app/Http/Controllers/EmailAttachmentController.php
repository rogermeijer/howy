<?php

namespace App\Http\Controllers;

use App\Jobs\Mail\AddAttachmentToKnowledge;
use App\Models\Email;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Add an attachment the classifier left out, or that came with mail Howy did
 * not read, to the knowledge base. Typed binding, so another account's email
 * 404s through the tenant scope.
 */
class EmailAttachmentController extends Controller
{
    public function store(Email $email, int $attachment): RedirectResponse
    {
        abort_unless(isset(($email->attachments ?? [])[$attachment]), 404);

        AddAttachmentToKnowledge::dispatch($email->id, $attachment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is being added to the knowledge base.', ['name' => ($email->attachments ?? [])[$attachment]['filename']])]);

        return back();
    }
}
