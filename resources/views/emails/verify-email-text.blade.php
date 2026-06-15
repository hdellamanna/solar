{{ __('mail.common.brand') }}

{{ __('mail.verify.greeting') }}

{{ __('mail.verify.intro', ['name' => $user->name]) }}
{{ __('mail.verify.expire') }}

{{ $verificationUrl }}

{{ __('mail.verify.footer') }}
