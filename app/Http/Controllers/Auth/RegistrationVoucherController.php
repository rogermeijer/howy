<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The code step in front of registration, while Howy is in private beta.
 *
 * This only checks a code and remembers it in the session; CreateNewUser checks
 * it again and redeems it, so skipping this step gets nobody in.
 */
class RegistrationVoucherController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $code = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ])['code'];

        $voucher = Voucher::findRedeemable($code);

        if ($voucher === null) {
            return back()->withErrors([
                'code' => __('That invite code is not valid. Check it, or request access below.'),
            ]);
        }

        $request->session()->put(Voucher::SESSION_KEY, $voucher->code);

        return to_route('register');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(Voucher::SESSION_KEY);

        return to_route('register');
    }
}
