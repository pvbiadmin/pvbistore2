<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Traits\ReferralTrait;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Facades\Hashids;

class UserReferralCodeController extends Controller
{
    use ReferralTrait;

    /**
     * View Referral Code Generation Page
     *
     * @return Application|Factory|View|\Illuminate\Foundation\Application
     */
    public function index()
    {
        return view('frontend.dashboard.referral-code.index');
    }

    /**
     * Generate Referral Code
     *
     * @return Application|ResponseFactory|\Illuminate\Foundation\Application|Response
     */
    public function generateCode()
    {
        if (!auth()->check()) {
            return response([
                'status' => 'error',
                'message' => 'Login required.'
            ], 402);
        }

        $code = Hashids::encode(auth()->user()->id);

        // Generate the referral link
        $referralLink = url('/referral/' . $code);

        return response([
            'status' => 'success',
            'message' => $referralLink
        ]);
    }

    /**
     * Send Referral Code to User
     *
     * @param Request $request
     * @return RedirectResponse|null
     */
    public function sendCode(Request $request): ?RedirectResponse
    {
        return $this->sendReferralCode($request);
    }
}
