@extends('fronts.layouts.app')
@section('front-title')
    {{ __('messages.about_us.about_us') }}
@endsection
@section('front-content')
    <div class="about-page">
        <!-- start about-content section -->
        <section class="about-content-section p-t-100 p-b-100">
            <div class="container">
                <div class="col-12">
                    <div class="hero-content max-w-1100 mx-auto ">
                        <h1 class="mb-5 pb-lg-4 text-center">
                            {{ $frontCMSSettings['about_us_title'] }}
                        </h1>
                        <p class="text-secondary fs-20 mb-0">
                            {!! $frontCMSSettings['about_us_description'] !!}
                        </p>
                    </div>
                </div>
            </div>
        </section>
        <!-- end about-content section -->
    </div>
@endsection

