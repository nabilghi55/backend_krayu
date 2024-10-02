@extends('front.layouts.app')

@section('content')
    <section class="section-5 pt-3 pb-3 mb-3 bg-white">
        <div class="container">
            <div class="light-font">
                <ol class="breadcrumb primary-color mb-0">
                    <li class="breadcrumb-item"><a class="white-text" href="{{ route("front.home") }}">Home</a></li>
                    <li class="breadcrumb-item active">Shop</li>
                </ol>
            </div>
        </div>
    </section>

    <section class="section-6 pt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-3 sidebar">
                    <div class="sub-title mt-5">
                        <h2>Price</h2>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <input type="text" class="js-range-slider" name="my_range" value="" />
                        </div>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="row pb-3">
                        <div class="col-12 pb-1">
                            <div class="d-flex align-items-center justify-content-end mb-4">
                                <div class="ml-2">
                                    <select name="sort" id="sort" class="form-control">
                                        <option value="latest" {{ ($sort == 'latest') ? 'selected' : '' }}>Latest</option>
                                        <option value="price_desc" {{ ($sort == 'price_desc') ? 'selected' : '' }}>Highest Price</option>
                                        <option value="price_asc" {{ ($sort == 'price_asc') ? 'selected' : '' }}>Lowest Price</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        @if ($products->isNotEmpty())
                            @foreach ($products as $product)
                            <div class="col-md-4">
                                <div class="card product-card">
                                    <div class="product-image position-relative" style="height: 250px; overflow: hidden;">
                                        <a href="{{ route("front.product", $product->slug) }}" class="product-img">
                                            @if (!empty($product->product_image))
                                                <img class="card-img-top h-100" src="{{ $product->product_image }}" style="object-fit: cover;"/>
                                            @else
                                                <img class="card-img-top h-100" src="{{ asset('admin-assets/img/default-150x150.png') }}" style="object-fit: cover;"/>
                                            @endif
                                        </a>

                                        <a onclick="addToWishlist({{ $product->id }})" class="whishlist" href="javascript:void(0);"><i class="far fa-heart"></i></a>

                                        <div class="product-action">
                                            @if ($product->track_qty == 'Yes')
                                                @if ($product->qty > 0)
                                                    <a class="btn btn-dark" href="javascript:void(0);" onclick="addToCart({{ $product->id }});">
                                                        <i class="fa fa-shopping-cart"></i> Add To Cart
                                                    </a>
                                                @else
                                                    <a class="btn btn-dark" href="javascript:void(0);" onclick="showOutOfStockAlert();">
                                                        Kehabisan Stock
                                                    </a>
                                                @endif
                                            @else
                                                <a class="btn btn-dark" href="javascript:void(0);" onclick="addToCart({{ $product->id }});">
                                                    <i class="fa fa-shopping-cart"></i> Add To Cart
                                                </a>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="card-body text-center mt-3" style="height: 100px;">
                                        <a class="h6 link" href="{{ route("front.product", $product->slug) }}">{{ $product->title }}</a>
                                        <div class="price mt-2">
                                            <span class="h5"><strong>Rp {{ number_format($product->price, 2) }}</strong></span>

                                            @if ($product->compare_price > 0)
                                                <span class="h6 text-underline"><del>Rp {{ number_format($product->compare_price, 2) }}</del></span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        @endif

                        <div class="col-md-12 pt-5">
                            {{ $products->withQueryString()->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('customJs')
    <script>
        rangeSlider = $(".js-range-slider").ionRangeSlider({
            type : "double",
            min : 0,
            max : 1000000,
            from : {{ $priceMin }},
            step : 100000,
            to: {{ $priceMax }},
            skin : "round",
            max_postfix: "+",
            prefix: "Rp ",
            onFinish: function() {
                apply_filters()
            }
        });

        var slider = $(".js-range-slider").data("ionRangeSlider");

        $("#sort").change(function(){
            apply_filters();
        });

        function apply_filters() {
            var url = '{{ url()->current() }}?';

            //Price Slider
            url += '&price_min=' + slider.result.from + '&price_max=' + slider.result.to;

            // Search
            var keyword = $("#search").val();
            if (keyword.length > 0) {
                url += '&search=' + keyword;
            }

            //Sorting Product
            url += '&sort=' + $("#sort").val()

            window.location.href = url;
        }
    </script>

    <script>
        function showOutOfStockAlert() {
            Swal.fire({
                icon: 'error',
                title: 'Out of Stock',
                text: 'Sorry, this product is currently out of stock.',
                confirmButtonText: 'OK'
            });
        }

        function addToCart(id) {
            $.ajax({
                url: '{{ route("front.addToCart") }}',
                type: 'post',
                data: { id: id },
                dataType: 'json',
                success: function (response) {
                    if (response.status == true) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Added to Cart ',
                            html: response.message,
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: 'info',
                            title: 'Product Already in Cart',
                            html: response.message,
                            confirmButtonText: 'OK',
                            footer: '<a href="{{ route('front.cart') }}">Go to Cart</a>'
                        });
                    }
                }
            });
        }

        function addToWishlist(id) {
            $.ajax({
                url: '{{ route("front.addToWishlist") }}',
                type: 'post',
                data: { id: id },
                dataType: 'json',
                success: function (response) {
                    if (response.status == true) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Added to Wishlist',
                            html: response.message,
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: 'info',
                            title: 'Product Already in Wishlist',
                            html: response.message,
                            confirmButtonText: 'OK',
                            footer: '<a href="{{ route('account.wishlist') }}">Go to Wishlist</a>'
                        });
                    }
                }
            });
        }
    </script>
@endsection
