<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Mail\Contact;
use App\Models\About;
use App\Models\EmailConfiguration;
use App\Models\TermsAndCondition;
use Exception;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PageController extends Controller
{
    /**
     * @return View|Application|Factory|\Illuminate\Contracts\Foundation\Application
     */
    public function about(): View|Application|Factory|\Illuminate\Contracts\Foundation\Application
    {
        $about = About::query()->first();

        return view('frontend.pages.about', compact('about'));
    }

    /**
     * @return View|Application|Factory|\Illuminate\Contracts\Foundation\Application
     */
    public function termsAndCondition(): View|Application|Factory|\Illuminate\Contracts\Foundation\Application
    {
        $terms = TermsAndCondition::query()->first();

        return view('frontend.pages.terms-and-condition', compact('terms'));
    }

    /**
     * @return View|Application|Factory|\Illuminate\Contracts\Foundation\Application
     */
    public function contact(): View|Application|Factory|\Illuminate\Contracts\Foundation\Application
    {
        return view('frontend.pages.contact');
    }

    /**
     * @param Request $request
     * @return Application|Response|\Illuminate\Contracts\Foundation\Application|ResponseFactory
     */
    public function handleContactForm(Request $request): Application|Response|\Illuminate\Contracts\Foundation\Application|ResponseFactory
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'max:200'],
            'email' => ['required', 'email'],
            'subject' => ['required', 'max:200'],
            'message' => ['required', 'max:1000']
        ]);

        try {
            $validator->validate();
        } catch (ValidationException $e) {
            $error = $e->validator->errors()->first();
            return response([
                'status' => 'error',
                'message' => $error
            ]);
        }

        $setting = EmailConfiguration::query()->first();

        if (!$setting || !$setting->email) {
            return response([
                'status' => 'error',
                'message' => 'Email configuration not found or missing email.'
            ]);
        }

        try {
            Mail::to($setting->email)->send(new Contact(
                $request->input('subject'),
                $request->input('message'),
                $request->input('email')
            ));
        } catch (Exception) {
            return response([
                'status' => 'error',
                'message' => 'Failed to send email. Please try again later.'
            ]);
        }

        return response(['status' => 'success', 'message' => 'Mail sent successfully!']);
    }
}
