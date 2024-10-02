<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopController extends Controller
{
    public function index(Request $request) {


    $products = Product::where('status',1);
    if ($request->get('price_max') != '' && $request->get('price_min') != '') {
        if ($request->get('price_max') == 1000000) {
            $products = $products->whereBetween('price',[intval($request->get('price_min')),1000000]);
        } else {
            $products = $products->whereBetween('price',[intval($request->get('price_min')),intval($request->get('price_max'))]);
        }

    }

    if(!empty($request->get('search'))) {
        $products = $products->where('title','like','%'.$request->get('search').'%');

    }

    //$products = Product::orderBy('id', 'DESC')->where('status',1)->get();
    if ($request->get('sort') != '') {
        if ($request->get('sort') == 'latest') {
            $products = $products->orderBy('id','DESC');
        } else if($request->get('sort') == 'price_asc') {
            $products = $products->orderBy('price','ASC');
        } else {
            $products = $products->orderBy('price','DESC');
        }
    } else {
        $products = $products->orderBy('id','DESC');
    }

    $products = $products->paginate(6);

    $data['products'] = $products;
    $data['priceMax'] = (intval($request->get('price_max')) == 0) ? 1000000 : $request->get('price_max');
    $data['priceMin'] = intval($request->get('price_min'));
    $data['sort'] = $request->get('sort');



    return view('front.shop', $data);
    }


    public function product($slug) {
        //$slug;
        $product = Product::where('slug', $slug)->first();
        if ($product == null) {
            abort(404);
        }

        $relatedProducts = [];
        // Fetch Related Products

        if ($product->related_products != '') {
            $productArray = explode(',',$product->related_products);

            $relatedProducts = Product::WhereIn('id', $productArray)->where('status',1)->get();
        }

        $data['product'] = $product;
        $data['relatedProducts'] = $relatedProducts;

        return view('front.product',$data);
    }

    public function saveRating($id, Request $request){
        $validator = Validator::make($request->all(),[
            'username' => 'required|min:5',
            'email' => 'required|email',
            'comment' => 'required|min:10',
            'rating' => 'required'

        ]);

        if ($validator->fails()){
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $count = ProductRating::where('email', $request->email)->count();
        if ($count > 0) {
           session()->flash('error','You already rated this product.');
           return response()->json([
                'status' => true,
                'alreadyRated' => true,
            ]);
        }

        $productRating = new ProductRating;
        $productRating->product_id = $id;
        $productRating->username = $request->username;
        $productRating->email = $request->email;
        $productRating->comment = $request->comment;
        $productRating->rating = $request->rating;
        $productRating->status = 0;
        $productRating->save();

        session()->flash('success','Thanks for your rating');

        return response()->json([
            'status' => true,
            'message' => 'Thanks for your rating'
        ]);

    }
}
