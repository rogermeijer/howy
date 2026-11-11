<?php

use App\Http\Controllers\AccountSwitcherController;
use App\Http\Controllers\Auth\RegistrationCodeController;
use App\Http\Controllers\BetaAccessRequestController;
use App\Http\Controllers\Colleagues\ColleagueController;
use App\Http\Controllers\CurrentAccountController;
use App\Http\Controllers\EmailAttachmentController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\EmailStatementController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\Knowledge\ApprovalController;
use App\Http\Controllers\Knowledge\DocumentController;
use App\Http\Controllers\Knowledge\DocumentFactSourceController;
use App\Http\Controllers\Knowledge\DocumentInspectorController;
use App\Http\Controllers\Knowledge\DocumentReviewController;
use App\Http\Controllers\Knowledge\DocumentVersionController;
use App\Http\Controllers\Knowledge\KnowledgeController;
use App\Http\Controllers\Knowledge\KnowledgeSearchController;
use App\Http\Controllers\Knowledge\KnowledgeTopicController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\Webhooks\GmailWebhookController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

// Valet serves `.webmanifest` as application/octet-stream; Chrome rejects that MIME type.
Route::get('site.webmanifest', function () {
    return response(
        File::get(resource_path('site.webmanifest')),
        headers: ['Content-Type' => 'application/manifest+json; charset=UTF-8'],
    );
})->name('site.webmanifest');

Route::inertia('/', 'welcome')->name('home');

Route::inertia('privacy-policy', 'legal/privacy-policy')->name('privacy-policy');
Route::inertia('terms-of-service', 'legal/terms-of-service')->name('terms-of-service');

// Deliberately public: the language switcher has to work on the marketing and
// auth screens, before there is an account to store the choice against.
Route::put('locale', [LocaleController::class, 'update'])->name('locale.update');

// Private beta: anyone can ask for an invite, and registration starts with a code.
Route::post('beta-access', [BetaAccessRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('beta-access.store');

Route::middleware('guest')->group(function () {
    Route::post('register/code', [RegistrationCodeController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('register.code.store');
    Route::delete('register/code', [RegistrationCodeController::class, 'destroy'])
        ->name('register.code.destroy');
    // The link in an invite mail.
    Route::get('register/invite/{code}', [RegistrationCodeController::class, 'show'])
        ->middleware('throttle:10,1')
        ->name('register.invite');
});

// Setup, once email is verified. Only for administrators: they are the ones
// sent here, and the only ones who can save what it asks.
Route::middleware(['auth', 'verified', 'tenant', 'account.admin'])->prefix('setup')->name('setup.')->group(function () {
    Route::get('/', [SetupController::class, 'start'])->name('start');
    Route::get('workspace', [SetupController::class, 'workspace'])->name('workspace');
    Route::patch('workspace', [SetupController::class, 'updateWorkspace'])->name('workspace.update');
    Route::get('knowledge', [SetupController::class, 'knowledge'])->name('knowledge');
    Route::patch('knowledge', [SetupController::class, 'updateKnowledge'])->name('knowledge.update');
    Route::get('mailbox', [SetupController::class, 'mailbox'])->name('mailbox');
    Route::post('finish', [SetupController::class, 'finish'])->name('finish');
    Route::get('done', [SetupController::class, 'done'])->name('done');
});

// set-up sends the administrators of an account that is not set up yet to
// setup first. Settings stay reachable without it: connecting Gmail comes
// back there.
Route::middleware(['auth', 'verified', 'tenant', 'set-up'])->group(function () {
    Route::get('dashboard', [InboxController::class, 'index'])->name('dashboard');
    Route::get('inbox', [InboxController::class, 'index'])->name('inbox');

    Route::get('emails/{email}', [EmailController::class, 'show'])->name('emails.show');

    // What a mail would add to the knowledge base: its admins decide, as they
    // curate it, from the knowledge base's approvals (knowledge.approvals).
    Route::middleware('account.admin')->group(function () {
        Route::post('emails/{email}/statements/{statement}/approve', [EmailStatementController::class, 'approve'])
            ->whereNumber('statement')
            ->name('emails.statements.approve');
        Route::post('emails/{email}/statements/{statement}/reject', [EmailStatementController::class, 'reject'])
            ->whereNumber('statement')
            ->name('emails.statements.reject');
        Route::post('emails/{email}/attachments/{attachment}', [EmailAttachmentController::class, 'store'])
            ->whereNumber('attachment')
            ->name('emails.attachments.store');
    });

    // The people who mail Howy: what it may do with their mail, where their
    // Howy mails stand, and what they did. Admins only.
    Route::middleware('account.admin')->prefix('colleagues')->name('colleagues.')->group(function () {
        Route::get('/', [ColleagueController::class, 'index'])->name('index');
        Route::post('/', [ColleagueController::class, 'store'])->name('store');
        Route::get('{colleague}', [ColleagueController::class, 'show'])->name('show');
        Route::patch('{colleague}', [ColleagueController::class, 'update'])->name('update');
        Route::put('{colleague}/mailboxes/{mailbox}', [ColleagueController::class, 'updateAccess'])->name('access.update');
    });

    Route::get('knowledge', [KnowledgeController::class, 'index'])->name('knowledge');

    Route::prefix('knowledge')->name('knowledge.')->group(function () {
        Route::get('search', [KnowledgeSearchController::class, 'index'])->name('search');
        Route::get('folders', [KnowledgeTopicController::class, 'index'])->name('topics.index');
        Route::get('folders/{topic}', [KnowledgeTopicController::class, 'show'])->name('topics.show');
        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('documents/{document}', [DocumentInspectorController::class, 'show'])->name('documents.show');
        Route::get('documents/{document}/facts/{fact}/source', DocumentFactSourceController::class)->name('documents.facts.source');
        Route::get('documents/{document}/versions/{version}/file', [DocumentVersionController::class, 'file'])
            ->scopeBindings()
            ->name('documents.versions.file');

        // Everyone in the account reads the knowledge base; its admins curate it.
        Route::middleware('account.admin')->group(function () {
            // Everything that waits for approval, documents and mail alike.
            Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals');

            Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
            Route::patch('documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
            Route::get('documents/{document}/removal', [DocumentController::class, 'removal'])->name('documents.removal');
            Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
            Route::post('documents/{document}/reprocess', [DocumentController::class, 'reprocess'])->name('documents.reprocess');
            Route::post('documents/{document}/versions', [DocumentVersionController::class, 'store'])->name('documents.versions.store');
            Route::post('documents/{document}/versions/{version}/approve', [DocumentReviewController::class, 'approve'])
                ->scopeBindings()
                ->name('documents.versions.approve');
            Route::post('documents/{document}/versions/{version}/reject', [DocumentReviewController::class, 'reject'])
                ->scopeBindings()
                ->name('documents.versions.reject');
            Route::patch('documents/{document}/versions/{version}/facts/{fact}/choice', [DocumentReviewController::class, 'choose'])
                ->scopeBindings()
                ->name('documents.versions.facts.choice');

            Route::post('folders', [KnowledgeTopicController::class, 'store'])->name('topics.store');
            Route::post('folders/approve', [KnowledgeTopicController::class, 'approveAll'])->name('topics.approve-all');
            Route::patch('folders/{topic}', [KnowledgeTopicController::class, 'update'])->name('topics.update');
            Route::post('folders/{topic}/approve', [KnowledgeTopicController::class, 'approve'])->name('topics.approve');
            Route::post('folders/{topic}/move', [KnowledgeTopicController::class, 'move'])->name('topics.move');
            Route::post('folders/{topic}/merge', [KnowledgeTopicController::class, 'merge'])->name('topics.merge');
            Route::delete('folders/{topic}', [KnowledgeTopicController::class, 'destroy'])->name('topics.destroy');
            Route::post('folders/{topic}/links', [KnowledgeTopicController::class, 'link'])->name('topics.links.store');
            Route::delete('folders/{topic}/links/{link}', [KnowledgeTopicController::class, 'unlink'])->scopeBindings()->name('topics.links.destroy');
        });
    });
});

// Not tenant-gated: someone healing out of a bad account state still needs to
// be able to switch, and switching must work before email verification.
Route::middleware(['auth'])->group(function () {
    Route::get('account-switcher', AccountSwitcherController::class)
        ->name('account-switcher');
    Route::put('current-account', [CurrentAccountController::class, 'update'])
        ->name('current-account.update');
});

// Gmail pushes here through Cloud Pub/Sub. No session and no CSRF token: the
// request is authenticated by the Google-signed OIDC token instead.
Route::post('webhooks/gmail', GmailWebhookController::class)
    ->withoutMiddleware([PreventRequestForgery::class])
    ->name('webhooks.gmail');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
