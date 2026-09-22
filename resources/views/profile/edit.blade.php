<x-app-layout>
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card bg-black border-warning mb-4 shadow">
                    <div class="card-header bg-warning text-dark border-0">
                        <h4 class="mb-0 fw-bold">{{ __('Profile Information') }}</h4>
                    </div>
                    <div class="card-body text-white">
                        <div class="max-w-xl">
                            @include('profile.partials.update-profile-information-form')
                        </div>
                    </div>
                </div>

                <div class="card bg-black border-warning mb-4 shadow">
                    <div class="card-header bg-warning text-dark border-0">
                        <h4 class="mb-0 fw-bold">{{ __('Update Password') }}</h4>
                    </div>
                    <div class="card-body text-white">
                        <div class="max-w-xl">
                            @include('profile.partials.update-password-form')
                        </div>
                    </div>
                </div>

                <div class="card bg-black border-danger mb-4 shadow">
                    <div class="card-header bg-danger text-white border-0">
                        <h4 class="mb-0 fw-bold">{{ __('Delete Account') }}</h4>
                    </div>
                    <div class="card-body text-white">
                        <div class="max-w-xl">
                            @include('profile.partials.delete-user-form')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
