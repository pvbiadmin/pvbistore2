<?php

namespace App\Http\Controllers\Frontend;

use App\DataTables\UserOrderDataTable;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;

class UserOrderController extends Controller
{
    /**
     * View All Orders
     *
     * @param UserOrderDataTable $dataTable
     * @return mixed
     */
    public function index(UserOrderDataTable $dataTable): mixed
    {
        return $dataTable->render('frontend.dashboard.order.index');
    }

    /**
     * View Order Details
     *
     * @param string $id
     * @return View|Application|Factory|\Illuminate\Contracts\Foundation\Application
     */
    public function show(string $id): View|Application|Factory|\Illuminate\Contracts\Foundation\Application
    {
        $order = Order::query()->with(['orderProducts'])->findOrFail($id);

        return view('frontend.dashboard.order.show', compact('order'));
    }
}
