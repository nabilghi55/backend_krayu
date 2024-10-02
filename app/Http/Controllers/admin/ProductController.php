<?php

namespace App\Http\Controllers\admin;

use App\Exports\ExportProduct;
use App\Http\Controllers\Controller;
use App\Imports\ProductImport;
use App\Models\Product;
use PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{

    public function index(Request $request)
    {
        $products = Product::oldest('id');

        if ($request->get('keyword') != "") {
            $products = $products->where('title', 'like', '%' . $request->keyword . '%');
        }

        $products = $products->paginate();
        $data['products'] = $products;
        return view('admin.product.list', $data);
    }

    public function create()
    {
        return view('admin.product.create');
    }

    public function store(Request $request)
    {
        // Validasi input
        $rules = [
            'title' => 'required',
            'slug' => 'required|unique:products',
            'product_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'price' => 'required|numeric',
            'sku' => 'required|unique:products',
            'track_qty' => 'required|in:Yes,No',
            'is_featured' => 'required|in:Yes,No',
        ];

        // Jika track_qty adalah Yes, qty juga harus required
        if ($request->track_qty == 'Yes') {
            $rules['qty'] = 'required|numeric';
        }

        // Validasi data
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        // Proses penyimpanan gambar
        $image = $request->file('product_image');
        $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
        $image->move(public_path('img/product_images'), $name_gen);
        $save_url = url('img/product_images/' . $name_gen);

        // Menyimpan data produk ke database
        $product = new Product;
        $product->title = $request->title;
        $product->slug = $request->slug;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->product_image = $save_url;  // Perbaiki di sini
        $product->compare_price = $request->compare_price;
        $product->sku = $request->sku;
        $product->track_qty = $request->track_qty;
        $product->qty = $request->track_qty == 'Yes' ? $request->qty : null;  // Perbaiki di sini
        $product->status = $request->status;
        $product->is_featured = $request->is_featured;
        $product->shipping_returns = $request->shipping_returns;
        $product->short_description = $request->short_description;
        $product->related_products = !empty($request->related_products) ? implode(',', $request->related_products) : '';
        $product->save();

        // Menyimpan pesan sukses di session
        $request->session()->flash('success', 'Product Added Successfully');

        return redirect()->route('products.index')->with('success', 'Product Added Successfully');
    }


    public function edit($id, Request $request)
    {

        $product = Product::find($id);

        if (empty($product)) {
            //$request->session()->flash('error', 'Product not Found');
            return redirect()->route('products.index')->with('error', 'Products not Found');
        }


        $data = [];
        $data['product'] = $product;
        return view('admin.product.edit', $data);
    }

    public function update($id, Request $request)
    {
        $product = Product::find($id);

        $rules = [
            'title' => 'required',
            'slug' => 'required|unique:products,slug,' . $product->id . ',id',
            'price' => 'required|numeric',
            'sku' => 'required|unique:products,sku,' . $product->id . ',id',
            'track_qty' => 'required|in:Yes,No',
            'is_featured' => 'required|in:Yes,No',
        ];

        // Additional validation for image if it is uploaded
        if ($request->hasFile('product_image')) {
            $request->validate([
                'product_image' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);
        }

        if (!empty($request->track_qty) && $request->track_qty == 'Yes') {
            $rules['qty'] = 'required|numeric';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->passes()) {
            // Handle image upload
            if ($request->hasFile('product_image')) {
                // Delete the old product image if it exists
                if ($product->product_image && file_exists(public_path('img/product_images/' . basename($product->product_image)))) {
                    unlink(public_path('img/product_images/' . basename($product->product_image)));
                }

                $image = $request->file('product_image');
                $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('img/product_images'), $name_gen);
                $save_url = url('img/product_images/' . $name_gen);

                // Update product image URL
                $product->product_image = $save_url;
            }

            // Update other product fields
            $product->title = $request->title;
            $product->slug = $request->slug;
            $product->description = $request->description;
            $product->price = $request->price;
            $product->compare_price = $request->compare_price;
            $product->sku = $request->sku;
            $product->track_qty = $request->track_qty;
            $product->qty = $request->qty;
            $product->status = $request->status;
            $product->is_featured = $request->is_featured;
            $product->shipping_returns = $request->shipping_returns;
            $product->short_description = $request->short_description;
            $product->related_products = (!empty($request->related_products)) ? implode(',', $request->related_products) : '';

            $product->save();

            $request->session()->flash('success', 'Product Updated Successfully');

            return response()->json([
                'status' => true,
                'message' => 'Product Updated Successfully'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }
    }



    public function destroy($id, Request $request)
    {
        $product = Product::find($id);

        if (empty($product)) {
            $request->session()->flash('error', 'Product Not Found');
            return response()->json([
                'status' => false,
                'notFound' => true
            ]);
        }
        $imagePath = $product->product_image;
        if ($imagePath) {
            $filename = public_path(str_replace(url('/'), '', $imagePath));
            if (file_exists($filename)) {
                unlink($filename);
            }
        }

        $product->delete();

        $request->session()->flash('success', 'Product Deleted Successfully');

        return response()->json([
            'status' => true,
            'message' => 'Product Deleted Successfully'
        ]);
    }

    public function getProducts(Request $request)
    {

        $tempProduct = [];
        if ($request->term != "") {
            $products = Product::where('title', 'like', '%' . $request->term . '%')->get();

            if ($products != null) {
                foreach ($products as $product) {
                    $tempProduct[] = array('id' => $product->id, 'text' => $product->title);
                }
            }
        }

        return response()->json([
            'tags' => $tempProduct,
            'status' => true
        ]);
    }

    public function export_excel()
    {
        return Excel::download(new ExportProduct, "product.csv");
    }

    public function import_excel()
    {
        try {
            Excel::import(new ProductImport, request()->file('file'));
            session()->flash('success', 'Excel file imported successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error importing Excel file: ' . $e->getMessage());
        }

        return back();
    }

    public function export_pdf()
    {
        // Retrieve order details and customer information
        $products = Product::all();
        foreach ($products as $product) {
            $product->description = strip_tags($product->description);
        }
        $data['products'] = $products;
        $now = Carbon::now()->format('Y-m-d');
        $data['now'] = $now;

        // Generate the PDF
        $pdf = PDF::loadView('admin.report.product', compact('data'));

        // Set options if needed (e.g., page size, orientation)
        $pdf->setPaper('A4', 'potrait');

        // Download the PDF with a specific filename
        return $pdf->stream('Product.pdf');
    }
}
