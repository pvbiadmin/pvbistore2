<?php

namespace App\Http\Controllers\Frontend;

use JsonException;
use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ChildCategory;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Subcategory;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Pagination\LengthAwarePaginator;

//use Psr\Container\ContainerExceptionInterface;
//use Psr\Container\NotFoundExceptionInterface;

class ProductController extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|Factory|View|Application
     * @throws JsonException
     */
    public function index(Request $request): Application|View|Factory|\Illuminate\Contracts\Foundation\Application
    {
        $query = Product::query()
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->with(['variants', 'category', 'imageGallery'])
            ->where('status', 1)
            ->where('is_approved', 1);

        if ($request->has('category')) {
            $query::whereCategoryId(Category::query()
                ->where('slug', $request->input('category'))->value('id'));
        } elseif ($request->has('subcategory')) {
            $query::whereSubcategoryId(Subcategory::query()
                ->where('slug', $request->input('subcategory'))->value('id'));
        } elseif ($request->has('childCategory')) {
            $query::whereChildCategoryId(ChildCategory::query()
                ->where('slug', $request->input('childCategory'))->value('id'));
        } elseif ($request->has('brand')) {
            $query::whereBrandId(Brand::query()
                ->where('slug', $request->input('brand'))->value('id'));
        } elseif ($request->has('search')) {
            $query->where(function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->input('search') . '%')
                    ->orWhere('long_description', 'like', '%' . $request->input('search') . '%');
            });
        }

        if ($request->has('range')) {
//            $price = explode(';', $request->input('range'));
            [$from, $to] = explode(';', $request->input('range'));
//            $from = $price[0];
//            $to = $price[1];

            $query->where('price', '>=', $from)->where('price', '<=', $to);
        }

        $perPage = $request->has('range') && session()->has('product_tab_view_style')
        && session('product_tab_view_style') === 'list' ? 6 : 12;

        $products = $query->orderBy('id', 'DESC')->paginate($perPage);

        $categories = Category::query()->where('status', 1)->get();
        $brands = Brand::query()->where('status', 1)->get();

        $product_page_banner_section = Advertisement::query()
            ->where('key', 'product_page_banner_section')->first();
        $product_page_banner_section = $product_page_banner_section
            ? json_decode($product_page_banner_section?->value, false, 512, JSON_THROW_ON_ERROR) : null;

        return view('frontend.pages.product',
            compact('products', 'categories', 'brands', 'product_page_banner_section'));
    }

    /**
     * View Product Details
     *
     * @param string $slug
     * @return View|Application|Factory|\Illuminate\Contracts\Foundation\Application
     */
    public function detail(string $slug): View|Application|Factory|\Illuminate\Contracts\Foundation\Application
    {
        $product = Product::query()
            ->with([
                'vendor',
                'category',
                'brand',
                'imageGallery',
                'variants'
            ])
            ->where('slug', '=', $slug)
            ->where('status', '=', 1)
            ->first();

        // If product is not found, create an empty paginator
        if (!$product) {
            $reviews = new LengthAwarePaginator([], 0, 1); // Empty paginator
            $flash_sale = null;
        } else {
            $flash_sale = FlashSale::query()->first();

            $reviews = ProductReview::query()->where([
                'product_id' => $product->id,
                'status' => 1
            ])->paginate(1);
        }

        return view('frontend.pages.product-detail',
            compact('product', 'flash_sale', 'reviews'));
    }

    public function changeProductTabView(Request $request): void
    {
        Session::put('product_tab_view_style', $request->input('style'));
    }

    public function changeProductDetailTabView(Request $request): void
    {
        Session::put('product_detail_tab_view_active', $request->input('style'));
    }
}
