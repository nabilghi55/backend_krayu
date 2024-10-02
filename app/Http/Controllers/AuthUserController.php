<?php
namespace App\Http\Controllers;

use App\Mail\ResetPasswordEmail;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Services\Midtrans\CreateSnapTokenService;
use Barryvdh\DomPDF\PDF as DomPDFPDF;
use PDF;

class AuthUserController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password], $request->remember)) {
            $user = Auth::user();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user,
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Invalid email or password.',
        ], 401);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|min:3',
            'email' => 'required|email|unique:users',
            'phone' => 'required',
            'password' => 'required|min:5|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Registration successful',
            'user' => $user,
        ], 201);
    }

    public function profile()
    {
        $user = Auth::user();

        return response()->json([
            'status' => true,
            'user' => $user,
            'countries' => Country::orderBy('name', 'ASC')->get(),
            'address' => CustomerAddress::where('user_id', $user->id)->first(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user->update($request->only(['name', 'email', 'phone']));

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }

    public function updateAddress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|min:5',
            'last_name' => 'required',
            'email' => 'required|email',
            'country_id' => 'required',
            'address' => 'required|min:30',
            'city' => 'required',
            'state' => 'required',
            'zip' => 'required',
            'mobile' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        CustomerAddress::updateOrCreate(
            ['user_id' => Auth::user()->id],
            $request->only(['first_name', 'last_name', 'email', 'mobile', 'country_id', 'address', 'apartment', 'city', 'state', 'zip'])
        );

        return response()->json([
            'status' => true,
            'message' => 'Address updated successfully',
        ]);
    }

    public function logout()
    {
        Auth::user()->tokens()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    public function orders()
    {
        $orders = Order::where('user_id', Auth::id())->orderBy('created_at', 'ASC')->get();

        return response()->json([
            'status' => true,
            'orders' => $orders,
        ]);
    }

    public function orderDetail($id)
    {
        $order = Order::where('user_id', Auth::id())->where('id', $id)->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $orderItems = OrderItem::where('order_id', $id)->with('product')->get();

        return response()->json([
            'status' => true,
            'order' => $order,
            'orderItems' => $orderItems,
        ]);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'new_password' => 'required|min:5',
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!Hash::check($request->old_password, Auth::user()->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Old password is incorrect',
            ], 400);
        }

        Auth::user()->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $token = Str::random(60);
        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        \DB::table('password_reset_tokens')->insert([
            'email' => $request->email,
            'token' => $token,
            'created_at' => now(),
        ]);

        $user = User::where('email', $request->email)->first();
        $formData = [
            'token' => $token,
            'user' => $user,
            'mailSubject' => 'Reset your password',
        ];

        Mail::to($request->email)->send(new ResetPasswordEmail($formData));

        return response()->json([
            'status' => true,
            'message' => 'Reset password email sent',
        ]);
    }

    public function resetPassword(Request $request, $token)
    {
        $tokenData = \DB::table('password_reset_tokens')->where('token', $token)->first();

        if (!$tokenData) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid token',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'new_password' => 'required|min:5',
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $tokenData->email)->first();
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        \DB::table('password_reset_tokens')->where('email', $tokenData->email)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Password has been reset successfully',
        ]);
    }
}
