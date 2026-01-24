@extends('fronts.layouts.app')
@section('front-title')
    {{ __('messages.home') }}
@endsection
@section('front-css')
    <link href="{{ asset('front/css/home.css') }}" rel="stylesheet" type="text/css">
@endsection
@section('front-content')
    <div class="home-page">
        <!-- start hero section -->
        <section class="hero-section p-t-100 p-b-100">
            <div class="container">
                <div class="row align-items-center flex-column-reverse flex-lg-row">
                    <div class="col-lg-6 text-lg-start text-center">
                        <div class="hero-content mt-5 mt-lg-0">
                            <h1 class="mb-3 pb-1">
                          {{ $frontCMSSettings['title']?? 'Default Title' }}
                            </h1>
                            <p class="fs-4 mb-lg-5 mb-4 pb-2">
                              {{$frontCMSSettings['description']?? 'Default Title'}}.</p>
                            @if(!getLogInUser())
                            <form action="{{ route('email.sign.up') }}">
                                <div class="input-group mb-md-3">
                                    <input type="email" name="email" class="form-control"
                                           placeholder="{{__('messages.placeholder.enter_your_email')}}" aria-label="{{__('messages.placeholder.enter_your_email')}}"
                                           aria-describedby="basic-addon2">
                                    <button type="submit" class="input-group-text px-5" id="basic-addon2" data-turbo="false">
                                        {{__('messages.web.get_started')}}
                                    </button>
                                </div>
                            </form>
                            @endif
                        </div>
                    </div>
                    <div class="col-lg-6 text-lg-end text-center">
                        <img src="{{ asset($frontCMSSettings['front_image']?? 'Default Title') }}" alt="img" class="img-fluid" loading="lazy"/>
                    </div>
                </div>
            </div>
        </section>
        <!-- end hero section -->

        <!-- start company logo section -->
  
        <!-- end company logo section -->

        <!-- start services section -->
        <section class="services-section p-t-100 p-b-100">
            <div class="container">
                <h2 class="m-b-60 max-w-620 text-center mx-auto">{{$data['services']['main_title'] ?? 'Default Title'}}</h2>
                <div class="row justify-content-center">
                    <div class="col-xl-4 col-md-6 service-block d-flex align-items-stretch">
                        <div class="card service-inner text-center mx-lg-2 flex-fill">
                            <div class="card-body d-flex flex-column">
                                <div class="service-icon pb-3 mb-4">
                                    <img src="{{ $data['services']['service_image_1']?? 'Default Title' }}" alt="smart-popups" class="img-fluid" loading="lazy">
                                </div>
                                <h4 class="mb-3">{{ $data['services']['service_title_1']?? 'Default Title' }}</h4>
                                <p class="fs-6 text-secondary fw-light mb-0">
                                    {{ $data['services']['service_description_1']?? 'Default Title' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6 service-block d-flex align-items-stretch mt-md-0 mt-4">
                        <div class="card service-inner text-center mx-lg-2 flex-fill">
                            <div class="card-body d-flex flex-column">
                                <div class="service-icon pb-3 mb-4">
                                    <img src="{{ $data['services']['service_image_2']?? 'Default Title' }}" alt="smart-popups" class="img-fluid" loading="lazy">
                                </div>
                                <h4 class="mb-3">{{ $data['services']['service_title_2'] ?? 'Default Title'}}</h4>
                                <p class="fs-6 text-secondary fw-light mb-0">
                                    {{ $data['services']['service_description_2']?? 'Default Title' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-md-6 service-block d-flex align-items-stretch mt-xl-0 mt-4 pt-xl-0 pt-lg-3">
                        <div class="card service-inner text-center mx-lg-2 flex-fill">
                            <div class="card-body d-flex flex-column">
                                <div class="service-icon pb-3 mb-4">
                                    <img src="{{ $data['services']['service_image_3']?? 'Default Title' }}" alt="smart-popups" class="img-fluid" loading="lazy">
                                </div>
                                <h4 class="mb-3">{{ $data['services']['service_title_3']?? 'Default Title' }}</h4>
                                <p class="fs-6 text-secondary fw-light mb-0">
                                    {{ $data['services']['service_description_3']?? 'Default Title' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- end services section -->

<section id="use-cases">
            <div class="container">
                <div class="section-header">
                    <h2>💼 Real-Life Magic: Use Cases</h2>
                </div>
                <div class="use-case-grid">
                    <div class="use-case-card">
                        <div class="card-icon">🏡</div>
                        <h3>House Hunter</h3>
                        <p>Separate links for brokers, owners, or listings. Track calls without chaos and keep your number private.</p>
                    </div>
                    <div class="use-case-card">
                        <div class="card-icon">🧑‍🏫</div>
                        <h3>Teachers & Professors</h3>
                        <p>Talk to students during scheduled hours only. Your privacy is protected and distractions are eliminated.</p>
                    </div>
                    <div class="use-case-card">
                        <div class="card-icon">❤️</div>
                        <h3>Matrimonial Profiles</h3>
                        <p>Share a link, not a number. Preferential callers get through, while others are silently blocked.</p>
                    </div>
                    <div class="use-case-card">
                        <div class="card-icon">✈️</div>
                        <h3>Frequent Travelers</h3>
                        <p>Airlines call a link you control for updates, upgrades, and delays, all without cluttering your main line.</p>
                    </div>
                    <div class="use-case-card">
                        <div class="card-icon">🎯</div>
                        <h3>Sales & Marketing Pros</h3>
                        <p>Let prospects book calls via a link in emails or forms. Appointments auto-open access, then shut down.</p>
                    </div>
                    <div class="use-case-card">
                        <div class="card-icon">👶</div>
                        <h3>Social Circles</h3>
                        <p>Share advice, recipes, or reviews in mom groups or clubs. Stay social without risking your phone number.</p>
                    </div>
                </div>
            </div>
        </section>
        <section id="business">
            <div class="container">
                <div class="business-content">
                    <div class="business-text">
                        <h2>🌐 For Businesses & Teams</h2>
                        <p>Elevate your customer experience and internal communications with smart, flexible call routing.</p>
                        <ul>
                            <li>Create shift-based links for support teams, banks, or hotel crews.</li>
                            <li>Clients just click—no memorizing numbers or asking who’s on duty.</li>
                            <li>Improve customer experience and eliminate frustrating routing delays.</li>
                        </ul>
                    </div>
                <div class="business-image">
                       <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" fill="none">
                           <path d="M78.53 175c-27.34 0-49.5-22.16-49.5-49.5s22.16-49.5 49.5-49.5" stroke="#ffffff" stroke-width="10" stroke-linecap="round"/>
                           <path d="M121.47 25c27.34 0 49.5 22.16 49.5 49.5s-22.16 49.5-49.5 49.5" stroke="#ffffff" stroke-width="10" stroke-linecap="round"/>
                           <circle cx="78.53" cy="76" r="21.5" fill="#34d399"/>
                           <circle cx="121.47" cy="124" r="21.5" fill="#34d399"/>
                       </svg>
                    </div>    
            
                </div>
            </div>
        </section>
        <section id="bonus">
            <div class="container">
                <div class="section-header">
                    <h2>🎬 Bonus Magic</h2>
                    <p>Discover clever ways CallALink simplifies everyday interactions.</p>
                </div>
                <div class="bonus-grid">
                    <div class="bonus-item">
                        <div class="card-icon">🍿</div>
                        <h3>Snacks at the Movies</h3>
                        <p>Link directly to the snack counter. No app, no hassle.</p>
                    </div>
                    <div class="bonus-item">
                        <div class="card-icon">✍️</div>
                        <h3>Public Reviews</h3>
                        <p>Let businesses respond to your review on your terms.</p>
                    </div>
                     <div class="bonus-item">
                        <div class="card-icon">👮</div>
                        <h3>Instant Routing</h3>
                        <p>Route calls to the closest police patrol or concierge desk.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- start reason-choose section -->
        

        <!-- start counts section -->
        <section class="counts-section bg-primary p-t-100 p-b-100">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-4 col-sm-6">
                        <div class="d-lg-flex text-center text-lg-start">
                            <div class="count-icon d-flex align-items-center justify-content-center">
                                <img src="{{ asset('front/images/registere-user.png')}}" alt="" loading="lazy">
                            </div>
                            <div class="ms-lg-4 ps-xxl-3">
                                <h3 class="fs-2 text-white fw-bolder">{{ $data['registeredUsersCount'] }}</h3>
                                <h4 class="text-white mb-0 fw-light">{{__('messages.web.registered_users') }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-sm-6 mt-sm-0 mt-4">
                        <div class="d-lg-flex text-center text-lg-start">
                            <div class="count-icon d-flex align-items-center justify-content-center">
                                <img src="{{asset('front/images/events-created.png')}}" alt="Events Created" loading="lazy">
                            </div>
                            <div class="ms-lg-4 ps-xxl-3">
                                <h3 class="fs-2 text-white fw-bolder">{{ $data['eventsCreatedCount'] }}</h3>
                                <h4 class="text-white mb-0 fw-light">{{ __('messages.web.events_created') }}</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-sm-6 mt-lg-0 mt-4 pt-lg-0 pt-md-3">
                        <div class="d-lg-flex text-center text-lg-start">
                            <div class="count-icon d-flex align-items-center justify-content-center">
                                <img src="{{asset('front/images/scheduled-events.png')}}" alt="Scheduled Events" loading="lazy" >
                            </div>
                            <div class="ms-lg-4 ps-xxl-3">
                                <h3 class="fs-2 text-white fw-bolder">{{ $data['scheduledEventsCount'] }}</h3>
                                <h4 class="text-white mb-0 fw-light">{{__('messages.web.scheduled_events')}}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- end counts section -->

       

        @endsection
        @section('front_scripts')
            <script>
                $('.testimonial-carousel').slick({
                    dots: false,
                    centerPadding: '0',
                    slidesToShow: 1,
                    slidesToScroll: 1,
                })
            </script>
@endsection
