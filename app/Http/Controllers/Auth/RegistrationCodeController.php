<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BetaAccessRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The code step in front of registration, while Howy is in private beta.
 *
 * This only checks a code and remembers it in the session; CreateNewUser checks
 * it again and redeems it, so skipping this step gets nobody in.
 */
class RegistrationCodeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $code = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ])['code'];

        $invite = BetaAccessRequest::findRedeemable($code);

        if ($invite === null) {
            return back()->withErrors([
                'code' => __('That invite code is not valid. Check it, or request access below.'),
            ]);
        }

        $request->session()->put(BetaAccessRequest::SESSION_KEY, $invite->code);

        return to_route('register');
    }

    /**
     * The link in an invite mail: the code is filled in, straight on to the form.
     */
    public function show(Request $request, string $code): RedirectResponse
    {
        $invite = BetaAccessRequest::findRedeemable($code);

        if ($invite === null) {
            return to_route('register')->withErrors([
                'code' => __('That invite code is not valid. Check it, or request access below.'),
            ]);
        }

        $request->session()->put(BetaAccessRequest::SESSION_KEY, $invite->code);

        return to_route('register');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(BetaAccessRequest::SESSION_KEY);

        return to_route('register');
    }
}
