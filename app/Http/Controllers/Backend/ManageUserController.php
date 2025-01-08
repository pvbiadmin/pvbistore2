<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Mail\AccountCreatedMail;
use App\Models\User;
use App\Models\Vendor;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
//use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Log;

class ManageUserController extends Controller
{
    /**
     * @return View|Application|Factory|\Illuminate\Contracts\Foundation\Application
     */
    public function index(): View|Application|Factory|\Illuminate\Contracts\Foundation\Application
    {
        return view('admin.manage-user.index');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function create(Request $request): RedirectResponse
    {
//        dd($request->all());
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'max:200'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['user', 'vendor', 'admin'])],
        ]);

        try {
            $validator->validate();
        } catch (ValidationException $e) {
            $error = $e->validator->errors()->first();
            return redirect()->back()->withInput()
                ->with(['message' => $error, 'alert-type' => 'error']);
        }

        $user = new User();

        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $user->password = bcrypt($request->input('password'));
        $user->role = $request->input('role');
        $user->status = 'active';

//        $user->fill([
//            'name' => $request->input('name'),
//            'email' => $request->input('email'),
//            'password' => bcrypt($request->input('password')),
//            'role' => $request->input('role'),
//            'status' => 'active'
//        ]);

        $user->save();

        if ($request->input('role') === 'vendor' || $request->input('role') === 'admin') {
            $vendor = new Vendor();

            $vendor->banner = 'uploads/1343.jpg';
            $vendor->shop_name = $request->input('name') . ' Shop';
            $vendor->phone = '12321312';
            $vendor->email = $user->email;
            $vendor->address = 'USA';
            $vendor->description = 'shop description';
            $vendor->user_id = $user->id;
            $vendor->status = 1;

//            $vendor->fill([
//                'banner' => 'uploads/1343.jpg',
//                'shop_name' => $request->input('name') . ' Shop',
//                'phone' => '12321312',
//                'email' => $user->email,
//                'address' => 'USA',
//                'description' => 'shop description',
//                'user_id' => $user->id,
//                'status' => 1,
//            ]);

            $vendor->save();
        }

        try {
            Mail::to($request->input('email'))
                ->send(new AccountCreatedMail(
                    $request->input('name'),
                    $request->input('email'),
                    $request->input('password')
                ));
        } catch (Exception $e) {
            // Log the error or just skip
            Log::error('Email failed to send: ' . $e->getMessage());
        }

        return redirect()->back()->with(['message' => 'Created Successfully!', 'alert-type' => 'success']);
    }
}
