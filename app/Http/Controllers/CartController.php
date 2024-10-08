<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\DiscountCoupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\ShippingCharge;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    public function addToCart(Request $request)
    {
        $product = Product::find($request->id);

        if ($product == null) {
            return response()->json([
                'status' => false,
                'message' => 'Product Not Found'
            ]);
        }

        $cartContent = Cart::content();
        $productAlreadyExist = false;

        foreach ($cartContent as $item) {
            if ($item->id == $product->id) {
                $productAlreadyExist = true;
            }
        }

        if ($productAlreadyExist) {
            // Product is already in the cart, display a message
            return response()->json([
                'status' => false,
                'message' => '<div class="alert alert-info"><strong>"' . $product->title . '"</strong> is already added in your cart</div>'
            ]);
        }

        // Add the product to the cart
        // Cart::add($product->id, $product->title, 1, $product->price, [
        //     $product->product_image,
        // ]);
        Cart::add([
            'id' => $product->id,
            'name' => $product->title,
            'qty' => 1,
            'price' => $product->price,
            'options' => [
                'image' => $product->product_image,
            ]
        ]);




        $message = '<strong>"' . $product->title . '"</strong> Added in Your Cart Successfully.';
        session()->flash('success', $message);


        return response()->json([
            'status' => true,
            'message' => $message
            // 'pesan' => $pesan,
        ]);
    }


    public function cart()
    {
        $cartContent = Cart::content();
        //dd($cartContent);
        $data['cartContent'] = $cartContent;
        return view('front.cart', $data);
    }

    public function updateCart(Request $request)
    {
        $rowId = $request->rowId;
        $qty = $request->qty;

        $itemInfo = Cart::get($rowId);

        $product = Product::find($itemInfo->id);
        //Check QTY Available in Stock
        if ($product->track_qty == 'Yes') {
            if ($qty <= $product->qty) {
                Cart::update($rowId, $qty);
                $message = 'Cart Updated Successfully';
                $status = true;
                session()->flash('success', $message);
            } else {
                $message = 'Requested QTY (' . $qty . ') Not Available in Stock.';
                $status = false;
                session()->flash('error', $message);
            }
        } else {
            Cart::update($rowId, $qty);
            $message = 'Cart Updated Successfully';
            $status = true;
            session()->flash('success', $message);
        }


        return response()->json([
            'status' => $status,
            'message' => $message
        ]);
    }

    public function deleteItem(Request $request)
    {

        $itemInfo = Cart::get($request->rowId);

        if ($itemInfo == null) {
            $errorMessage = 'Item Not Found in Cart';
            session()->flash('error', $errorMessage);

            return response()->json([
                'status' => false,
                'message' => $errorMessage
            ]);
        }

        Cart::remove($request->rowId);

        $message = 'Item Removed from Cart Successfully';
        session()->flash('success', $message);
        return response()->json([
            'status' => true,
            'message' => $message
        ]);
    }

    public function checkout(){

        $discount = 0;

        if (Cart::count() == 0) {
            return redirect()->route('front.cart');
        }

        if (Auth::check() == false) {

            if (!session()->has('url.intended')) {
                session(['url.intended' => url()->current()]);
            }

            return redirect()->route('account.login');
        }

        $customerAddress = CustomerAddress::where('user_id',Auth::user()->id)->first();


        session()->forget('url.intended');

        $countries = Country::orderBy('name','ASC')->get();

        $subTotal = Cart::subtotal(2,'.','');

        //Discount
        if (session()->has('code')) {

            $code = session()->get('code');
            if ($code->type == 'percent') {
                $discount = ($code->discount_amount/100)*$subTotal;
            } else {
                $discount = $code->discount_amount;
            }
        }

        //Hitung Shipping
        if ($customerAddress != '') {
            $userCountry = $customerAddress->country_id;
            $shippingInfo = ShippingCharge::where('country_id', $userCountry)->first();

            $totalQty = 0;
            $totalShippingCharge = 0;
            $grandTotal = 0;
            foreach (Cart::content() as $item) {
                $totalQty += $item->qty;
            }

            if ($shippingInfo != null) {
                $totalShippingCharge = $totalQty * $shippingInfo->amount;
                $grandTotal = ($subTotal-$discount) + $totalShippingCharge;
            } else {
                // Handle the case where $shippingInfo is null.
                // You can set default values for $totalShippingCharge and $grandTotal here.
            }
        } else {
            $grandTotal = ($subTotal-$discount);
            $totalShippingCharge = 0;
        }


        return view('front.checkout',[
            'countries' => $countries,
            'customerAddress' => $customerAddress,
            'totalShippingCharge' => $totalShippingCharge,
            'discount' => $discount,
            'grandTotal' => $grandTotal,
        ]);
    }
    public function checkoutApi(Request $request)
    {
        // Validasi request body
        $validatedData = $request->validate([
            'discount_code' => 'nullable|string',
            'customer_address_id' => 'required|integer',
            'cart_items' => 'required|array',
            'cart_items.*.id' => 'required|integer',
            'cart_items.*.qty' => 'required|integer|min:1',
        ]);
    
        $discount = 0;
        $customerAddress = CustomerAddress::find($validatedData['customer_address_id']);
    
        if (!$customerAddress) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer address not found'
            ], 404);
        }
    
        $subTotal = 0;
    
        // Calculate subtotal based on cart items
        foreach ($validatedData['cart_items'] as $item) {
            $product = Product::find($item['id']);
            if (!$product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found with id: ' . $item['id']
                ], 404);
            }
            $subTotal += $product->price * $item['qty'];
        }
    
        // Handle discount code if applicable
        // if (!empty($validatedData['discount_code'])) {
        //     $code = DiscountCode::where('code', $validatedData['discount_code'])->first();
        //     if ($code) {
        //         if ($code->type == 'percent') {
        //             $discount = ($code->discount_amount / 100) * $subTotal;
        //         } else {
        //             $discount = $code->discount_amount;
        //         }
        //     }
        // }
    
        // Calculate shipping
        $shippingInfo = ShippingCharge::where('country_id', $customerAddress->country_id)->first();
        $totalShippingCharge = 0;
        if ($shippingInfo) {
            $totalQty = array_sum(array_column($validatedData['cart_items'], 'qty'));
            $totalShippingCharge = $totalQty * $shippingInfo->amount;
        }
    
        // Calculate grand total
        $grandTotal = ($subTotal - $discount) + $totalShippingCharge;
    
        // Response JSON
        return response()->json([
            'status' => 'success',
            'subTotal' => $subTotal,
            'discount' => $discount,
            'totalShippingCharge' => $totalShippingCharge,
            'grandTotal' => $grandTotal,
        ]);
    }
    
    
    public function processCheckout(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'first_name' => 'required|min:5',
            'last_name' => 'required',
            'email' => 'required|email',
            'country' => 'required',
            'address' => 'required|min:30',
            'city' => 'required',
            'state' => 'required',
            'zip' => 'required',
            'mobile' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Please Fix the Errors',
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }


        $user = Auth::user();

        CustomerAddress::updateOrCreate(
            ['user_id' => $user->id],
            [
                'user_id' => $user->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'country_id' => $request->country,
                'address' => $request->address,
                'apartment' => $request->apartment,
                'city' => $request->city,
                'state' => $request->state,
                'zip' => $request->zip,

            ]
        );

        if ($request->payment_method == 'cod') {

            $discountCodeId = NULL;
            $promoCode = '';
            $shipping = 0;
            $discount = 0;
            $subTotal = Cart::subtotal(2, '.', '');

            if (session()->has('code')) {
                $code = session()->get('code');
                if ($code->type == 'percent') {
                    $discount = ($code->discount_amount / 100) * $subTotal;
                } else {
                    $discount = $code->discount_amount;
                }

                $discountCodeId = $code->id;
                $promoCode = $code->code;
            }

            $shippingInfo = ShippingCharge::where('country_id', $request->country)->first();

            $totalQty = 0;
            foreach (Cart::content() as $item) {
                $totalQty += $item->qty;
            }

            if ($shippingInfo != null) {
                $shipping = $totalQty * $shippingInfo->amount;
                $grandTotal = ($subTotal - $discount) + $shipping;
            } else {
                $shippingInfo = ShippingCharge::where('country_id', 'rest_of_world')->first();
                $shipping = $totalQty * $shippingInfo->amount;
                $grandTotal = ($subTotal - $discount) + $shipping;
            }


            $order = new Order;
            $order->subtotal = $subTotal;
            $order->shipping = $shipping;
            $order->grand_total = $grandTotal;
            $order->discount = $discount;
            $order->coupon_code_id = $discountCodeId;
            $order->coupon_code = $promoCode;
            $order->payment_status = '1';
            $order->status = 'pending';
            $order->user_id = $user->id;
            $order->first_name = $request->first_name;
            $order->last_name = $request->last_name;
            $order->email = $request->email;
            $order->mobile = $request->mobile;
            $order->address = $request->address;
            $order->apartment = $request->apartment;
            $order->state = $request->state;
            $order->city = $request->city;
            $order->zip = $request->zip;
            $order->notes = $request->order_notes;
            $order->country_id = $request->country;
            $order->save();



            foreach (Cart::content() as $item) {
                $orderItem = new OrderItem;
                $orderItem->product_id = $item->id;
                $orderItem->order_id = $order->id;
                $orderItem->name = $item->name;
                $orderItem->qty = $item->qty;
                $orderItem->price = $item->price;
                $orderItem->total = $item->price * $item->qty;
                $orderItem->save();

                //Update Produk Kuantitas
                $productData = Product::find($item->id);
                if ($productData->track_qty == 'Yes') {
                    $currentQty = $productData->qty;
                    $updatedQty = $currentQty - $item->qty;
                    $productData->qty = $updatedQty;
                    $productData->save();
                }
            }
            session()->flash('success', 'You Have Successfully Placed Your Order.');

            Cart::destroy();

            session()->forget('code');

            return response()->json([
                'message' => 'Order Saved Successfully',
                'orderId' => $order->id,
                'status' => true
            ]);
        }
    }
    public function processCheckoutApi(Request $request)
{
    // Validasi request body
    $validator = Validator::make($request->all(), [
        'first_name' => 'required|min:5',
        'last_name' => 'required',
        'email' => 'required|email',
        'country' => 'required',
        'address' => 'required|min:30',
        'city' => 'required',
        'state' => 'required',
        'zip' => 'required',
        'mobile' => 'required',
        'payment_method' => 'required|in:cod'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Please Fix the Errors',
            'status' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    // Mengambil user yang sedang login melalui token API
    $user = Auth::guard('api')->user();

    // Menyimpan atau memperbarui alamat pelanggan
    CustomerAddress::updateOrCreate(
        ['user_id' => $user->id],
        [
            'user_id' => $user->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'country_id' => $request->country,
            'address' => $request->address,
            'apartment' => $request->apartment,
            'city' => $request->city,
            'state' => $request->state,
            'zip' => $request->zip,
        ]
    );

    // Proses checkout dengan metode pembayaran COD
    if ($request->payment_method == 'cod') {
        $discountCodeId = null;
        $promoCode = '';
        $shipping = 0;
        $discount = 0;
        $subTotal = Cart::subtotal(2, '.', '');

        if (session()->has('code')) {
            $code = session()->get('code');
            $discount = ($code->type == 'percent')
                ? ($code->discount_amount / 100) * $subTotal
                : $code->discount_amount;

            $discountCodeId = $code->id;
            $promoCode = $code->code;
        }

        $shippingInfo = ShippingCharge::where('country_id', $request->country)->first();
        $totalQty = Cart::content()->sum('qty');
        $shipping = $shippingInfo ? $totalQty * $shippingInfo->amount : 0;
        $grandTotal = ($subTotal - $discount) + $shipping;

        $order = Order::create([
            'subtotal' => $subTotal,
            'shipping' => $shipping,
            'grand_total' => $grandTotal,
            'discount' => $discount,
            'coupon_code_id' => $discountCodeId,
            'coupon_code' => $promoCode,
            'payment_status' => '1',
            'status' => 'pending',
            'user_id' => $user->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'address' => $request->address,
            'apartment' => $request->apartment,
            'state' => $request->state,
            'city' => $request->city,
            'zip' => $request->zip,
            'notes' => $request->order_notes,
            'country_id' => $request->country,
        ]);

        foreach (Cart::content() as $item) {
            OrderItem::create([
                'product_id' => $item->id,
                'order_id' => $order->id,
                'name' => $item->name,
                'qty' => $item->qty,
                'price' => $item->price,
                'total' => $item->price * $item->qty,
            ]);

            // Update kuantitas produk
            $productData = Product::find($item->id);
            if ($productData && $productData->track_qty == 'Yes') {
                $productData->decrement('qty', $item->qty);
            }
        }

        Cart::destroy();
        session()->forget('code');

        return response()->json([
            'message' => 'Order Saved Successfully',
            'orderId' => $order->id,
            'status' => true
        ]);
    }

    return response()->json(['message' => 'Invalid payment method', 'status' => false], 400);
}





    public function thankyou($id, Order $order)
    {

        $data = [];
        $user = Auth::user();
        $order = Order::where('user_id', $user->id)->where('id', $id)->first();
        $data['order'] = $order;
        return view('front.thanks', compact('data', 'id'));
    }

    public function getOrderSummary(Request $request)
    {
        $subTotal = Cart::subtotal(2, '.', '');
        $discount = 0;
        $discountString = '';

        //discount
        if (session()->has('code')) {
            $code = session()->get('code');
            if ($code->type == 'percent') {
                $discount = ($code->discount_amount / 100) * $subTotal;
            } else {
                $discount = $code->discount_amount;
            }

            $discountString = '<div class="mt-4" id="discount-response">
                <strong>' . session()->get('code')->code . '</strong>
                <a class="btn btn-sm btn-danger" id="remove-discount"><i class="fa fa-times"></i></a>
            </div>';
        }

        if ($request->country_id > 0) {

            $shippingInfo = ShippingCharge::where('country_id', $request->country_id)->first();

            $totalQty = 0;
            foreach (Cart::content() as $item) {
                $totalQty += $item->qty;
            }

            if ($shippingInfo != null) {

                $shippingCharge = $totalQty * $shippingInfo->amount;
                $grandTotal = ($subTotal - $discount) + $shippingCharge;

                return response()->json([
                    'status' => true,
                    'grandTotal' => number_format($grandTotal, 2),
                    'discount' => number_format($discount, 2),
                    'discountString' => $discountString,
                    'shippingCharge' => number_format($shippingCharge, 2),
                ]);
            } else {

                $shippingInfo = ShippingCharge::where('country_id', 'rest_of_world')->first();

                $shippingCharge = $totalQty * $shippingInfo->amount;
                $grandTotal = ($subTotal - $discount) + $shippingCharge;

                return response()->json([
                    'status' => true,
                    'grandTotal' => number_format($grandTotal, 2),
                    'discount' => number_format($discount, 2),
                    'discountString' => $discountString,
                    'shippingCharge' => number_format($shippingCharge, 2),
                ]);
            }
        } else {
            return response()->json([
                'status' => true,
                'grandTotal' => number_format(($subTotal - $discount), 2),
                'discount' => number_format($discount, 2),
                'discountString' => $discountString,
                'shippingCharge' => number_format(0, 2),
            ]);
        }
    }

    public function applyDiscount(Request $request)
    {

        if (!Auth::check()) {
            return response()->json([
                'status' => false,
                'message' => 'User is Not Authenticated.',
            ]);
        }

        $code = DiscountCoupon::where('code', $request->code)->first();

        if ($code == null) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid Discount Coupon',
            ]);
        }

        //check coupon validation
        $now = Carbon::now('Asia/Jakarta');

        // echo $now->format('Y-m-d H:i:s');

        // Check if the coupon has a start date and if it's greater than the current date
        if ($code->starts_at != "") {
            $startDate = Carbon::createFromFormat('Y-m-d H:i:s', $code->starts_at);

            if ($now->lt($startDate)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Coupon is Not Valid Yet',
                ]);
            }
        }

        // Check if the coupon has an expiration date and if it's less than the current date
        if ($code->expires_at != "") {
            $endDate = Carbon::createFromFormat('Y-m-d H:i:s', $code->expires_at);

            if ($now->gt($endDate)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Coupon Has Expired',
                ]);
            }
        }

        //Max Uses
        if ($code->max_uses > 0) {
            $couponUsed = Order::where('coupon_code_id', $code->id)->count();

            if ($couponUsed >= $code->max_uses) {
                return response()->json([
                    'status' => false,
                    'message' => 'Coupon Has Expired Due to Reaching the Maximum Allowed Uses.',
                ]);
            }
        }

        //Max Uses per User
        if ($code->max_uses_user > 0) {
            $couponUsedUser = Order::where(['coupon_code_id' => $code->id, 'user_id' => Auth::user()->id])->count();

            if ($couponUsedUser >= $code->max_uses_user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Coupon Has Expired for Your Account Due to Reaching the Maximum Allowed Uses.',
                ]);
            }
        }

        //Check Min Amount
        $subTotal = Cart::subtotal(2, '.', '');

        if ($code->min_amount > 0) {
            if ($subTotal < $code->min_amount) {
                return response()->json([
                    'status' => false,
                    'message' => 'To Use This Coupon, Your Minimum Order Amount Must be At Least Rp ' . number_format($code->min_amount) . '. Please add more items to your cart to meet this requirement.',
                ]);
            }
        }

        session()->put('code', $code);

        return $this->getOrderSummary($request);
    }

    public function removeCoupon(Request $request)
    {
        session()->forget('code');
        return $this->getOrderSummary($request);
    }

    public function getCartItemCount(Request $request)
    {
        // Get the cart item count using the Shoppingcart package
        $cartItemCount = Cart::count();

        return response()->json([
            'status' => true,
            'itemCount' => $cartItemCount,
        ]);
    }
}
