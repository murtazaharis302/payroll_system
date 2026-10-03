<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-4">
            <div class="modal-header border-0 text-center">
                <h2 class="modal-title w-100" id="registerModalLabel">{{ __('messages.register') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-center">{{ __('messages.already_have_an_account') }} <a href="#"
                        class="login-link-text" data-bs-toggle="modal"
                        data-bs-target="#loginModal">{{ __('messages.login') }}</a></p>

                <form action="{{ route('user.signup') }}" id="usersignup">

                    @csrf
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-user icon-size"></i></span>

                            <input id="name" type="text" class="form-control"
                                placeholder="{{ __('messages.enter_name') }}">

                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-envelope icon-size"></i></span>

                            <input id="email" type="email" class="form-control"
                                placeholder="{{ __('messages.enter_email') }}">

                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-phone icon-size"></i></span>

                            <input id="phone" class="form-control" placeholder="{{ __('messages.phone') }}">

                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-lock icon-size"></i></span>

                            <input id="password" type="password" class="form-control"
                                placeholder="{{ __('messages.enter_password') }}">

                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-lock icon-size"></i></span>

                            <input id="password_confirmation" type="password" class="form-control"
                                placeholder="{{ __('messages.confirm_password') }}">

                        </div>
                    </div>

                    <button type="submit" class="btn w-100 btn-color mb-2">{{ __('messages.sign_up_now') }}</button>
                    <a href="{{ route('google.redirect') }}" class="btn w-100 btn-outline-danger mt-2">
                        <i class="fa-brands fa-google me-2"></i> {{ __('messages.continue_with_google') }}
                    </a>

                </form>
            </div>
        </div>
    </div>
</div>

<!-- Login Modal -->
