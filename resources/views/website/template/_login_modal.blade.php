<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-4">
            <div class="modal-header border-0 text-center">
                <h2 class="modal-title w-100" id="loginModalLabel">{{ __('messages.log_in') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-center">{{ __("messages.don't_have_account") }} <a href="#"
                        class="login-link-text" data-bs-toggle="modal" data-bs-target="#registerModal">Register</a>
                </p>
                <form action="{{ route('user.signin') }}" id="usersignin" method="post">
                    @csrf
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-envelope icon-size"></i></span>
                            <input type="email" id="get-email" class="form-control"
                                placeholder="{{ __('messages.enter_email') }}">
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-lock icon-size"></i></span>
                            <input type="password" id="get-password" class="form-control"
                                placeholder="{{ __('messages.enter_password') }}">
                        </div>
                    </div>

                    <button type="submit" class="btn w-100 btn-color mb-2">{{ __('messages.login') }}</button>
                    <a href="{{ route('google.redirect') }}" class="btn w-100 btn-outline-danger mt-2">
                        <i class="fa-brands fa-google me-2"></i> {{ __('messages.continue_with_google') }}
                    </a>

                </form>
            </div>
        </div>
    </div>
</div>

<!-- End Register modal and Login modal for web view  -->


<!-- car rental register and login button modal -->
