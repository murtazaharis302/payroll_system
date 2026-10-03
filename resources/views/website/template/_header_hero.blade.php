<!-- Hero Section Start -->
@if (request()->is('/'))
    <div class="hero-section text-center text-white">
        <h1 class="display-6 fw-bold">
            <span class="custom-bg-warning">{{ __('messages.rent') }}</span> {{ __('messages.vehicle_anytime_anywhere') }}

        </h1>
        {{-- <h1 class="display-5 fw-bold">{{ __('messages.anywhere') }}</h1> --}}
        {{-- <select id="locationDropdown" name="location_id" class="form-select"
            data-url="{{ route('car.locations') }}">
            <option disabled selected>{{ __('messages.select_location') }}</option>
            </select> --}}
        <div class="tabpanel-form mx-auto">
            <form id="carSearchForm" action="{{ route('car.search') }}" method="POST"
                enctype="multipart/form-data" class="row g-2">
                @csrf
                <div class="col-md-12">
                    <div class="row g-2">
                        <div class="col-md-6 col-12 position-relative">
                            <input type="text" id="area_name" name="area_name" class="form-control custom-input-header time-input"

                                placeholder="{{ __('messages.select_location') }}" autocomplete="off">

                            <ul id="locationSuggestions" class="list-group position-absolute w-100"
                                style="z-index: 1000;"></ul>
                        </div>

                        <div class="col-md-6 col-12">
                            <div class="datetime-wrapper" data-placeholder="Select Date & Time">
                                <input type="datetime-local" name="date" class="form-control real-datetime">
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-3 col-6">
                            <select id="brandDropdown" data-label="{{ __('messages.brand') }}" name="brand" class="form-select"
                                data-url="{{ route('car.brands') }}">
                                <option disabled selected>{{ __('messages.brands') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-6">
                            <select id="modelDropdown" data-label="{{ __('messages.models') }}" name="model" class="form-select">
                                <option disabled selected>{{ __('messages.models') }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3 col-6">
                            <select id="priceDropdown" name="Rent" class="form-select">
                                <option disabled selected>{{ __('messages.rent_search') }}</option>
                                <option value="0-500">0 - 500</option>
                                <option value="501-1000">501 - 1000</option>
                                <option value="1001-1500">1001 - 1500</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-6">
                            <select id="transmissionDropdown" data-label="{{ __('messages.transmission') }}" name="transmission" class="form-select">
                                <option disabled selected>{{ __('messages.transmission') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <button type="submit"
                        class="btn btn-orange-clr w-100 d-flex justify-content-center text-white align-items-center">
                        {{ __('messages.search') }} <i class="fa-solid fa-magnifying-glass text-white ms-3"></i>
                    </button>
                </div>
            </form>
        </div>
        <img src="{{ asset('/') }}frontend-assets/assets/apps-bg-1-300x123.png" alt="Car" class="car-image">


    </div>
@else
    <!-- Start -->
    <section class="join-program text-center text-white position-relative">
        <?php
        $clsHeight = 'text-height';
        if (request()->is('cardetail/*')) {
            $clsHeight = '';
        }
        ?>
        <div class="container {{ $clsHeight }}">
            @if (request()->is('carlisting'))
                <h2 class="fw-bold">{{ __('messages.search_result_page') }}</h2>
            @elseif(request()->is('carbooking') || request()->is('confirmation'))
                <h2 class="fw-bold">{{ __('messages.booking') }}</h2>
            @elseif(request()->is('checkout'))
                <h2 class="fw-bold">{{ __('messages.your_trusted_partner') }}</h2>
                <p>{{ __('messages.reliable_and_round') }}</p>
            @elseif(request()->is('categories'))
                <h2 class="fw-bold">{{ __('messages.categories') }}</h2>
                <p>{{ __('messages.find_the_perfect_ride:') }}</p>
            @elseif(request()->is('join-our-program'))
                <h2 class="fw-bold">{{ __('messages.join') }} {{ __('messages.our') }} {{ __('messages.program_heading') }}
                </h2>
                <p>{{ __('messages.an_easy_way') }}</p>
            @elseif(request()->is('about-us'))
                <h2 class="fw-bold">{{ __('messages.about_us') }}</h2>
                <p>{{ __('messages.your_friendly_vehicle') }}</p>
            @elseif(request()->is('cardetail'))
                <h2 class="fw-bold">{{ __('messages.vehicle_details') }}</h2>
                <p>{{ __('messages.find_the_perfect') }} –
                    {{ __('messages.explore_compare') }}</p>
            @elseif(request()->is('blog'))
                <h2 class="fw-bold">{{ __('messages.blog') }}</h2>
            @elseif(request()->is('blog-detail/*'))
                <h2 class="fw-bold">{{ __('messages.blog') }}</h2>
            @elseif(request()->is('booking'))
                <h2 class="fw-bold">{{ __('messages.header_booking') }}</h2>
            @elseif(request()->is('terms&conditions'))
                <h2 class="fw-bold">{{ __('messages.header_term') }}</h2>
            @elseif(request()->is('privacy-policy'))
                <h2 class="fw-bold">{{ __('messages.header_privacy') }}</h2>
            @elseif(request()->is('dashboard'))
                <h2 class="fw-bold">{{ __('messages.header_dash') }}</h2>
            @elseif(request()->is('booking-detail/*'))
                <h2 class="fw-bold">{{ __('messages.header_book_detail') }}</h2>
            @elseif(request()->is('edit-profile/*'))
                <h2 class="fw-bold">{{ __('messages.update_profile') }}</h2>
            @elseif(request()->is('faqs'))
                <h2 class="fw-bold">{{ __('messages.FAQ') }}</h2>
                <p>{{ __('messages.got_questions') }}</p>
            @elseif(request()->is('contact'))
                <style>
                    .text-height{
                    height: 165px!important;
                    }
                </style>
                <h2 class="fw-bold">{{ __('messages.contact_us') }}</h2>
                <p>{{__('messages.contact_text')}}</p>
            @elseif(request()->is('register-car-rental'))
                <h2 class="fw-bold">{{ __('messages.company_register') }}</h2>
            @endif
        </div>
        <div class="curve-bg"></div>
    </section>
@endif
</div>





<!-- Register modal and Login modal for web view  -->


<!-- Register Modal -->
