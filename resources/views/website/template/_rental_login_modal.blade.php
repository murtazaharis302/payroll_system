<div class="modal fade" id="carRentalModal" tabindex="-1" aria-labelledby="carRentalModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered rental-custom-modal">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: none;padding-bottom: 0;">
                <button class="back-arrow" data-bs-dismiss="modal" aria-label="Close">
                    ←
                </button>
            </div>
            <div class="modal-body renteal-body">
                <img src="{{ asset('/') }}frontend-assets/icons/rental-modal-popup-img.png" alt="Illustration"
                    class="rental-header-img">
                <button class="rental-register-btn"
                    onclick="window.location.href='{{ url('/register-with-car-rental') }}'">
                    <img src="{{ asset('/') }}frontend-assets/icons/car-rental-register-btn-icon.png"
                        class="rental-btn-icon" alt="Register">
                    {{ __('messages.register_with_select_and_rental') }}
                </button>

                <button class="rental-login-btn" onclick="window.location.href='{{ url('/company/login') }}'">
                    <img src="{{ asset('/') }}frontend-assets/icons/car-rental-login-btn-icon.png"
                        class="rental-btn-icon" alt="Login">
                    {{ __('messages.login_with_select_and_rental') }}
                </button>
            </div>
        </div>
    </div>
</div>
