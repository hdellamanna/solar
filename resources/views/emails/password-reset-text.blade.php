{{ __('mail.common.brand') }}

{{ __('mail.reset.greeting') }}

{{ __('mail.reset.intro', ['name' => $user->name]) }}
{{ __('mail.reset.expire') }}

{{ $resetUrl }}

{{ __('mail.reset.footer') }}
