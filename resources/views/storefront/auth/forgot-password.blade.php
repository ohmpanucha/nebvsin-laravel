@extends('layouts.storefront')

@section('content')
    <section class="login-page">
        <article class="login-card reveal in-view" aria-labelledby="forgot-password-title">
            <p class="login-kicker">{{ $copy['kicker'] }}</p>
            <h1 id="forgot-password-title" class="login-title">{{ $copy['forgot_password_title'] }}</h1>
            <p class="login-caption">{{ $copy['forgot_password_caption'] }}</p>

            <form class="login-form" action="{{ route('storefront.password.email', ['lang' => $storefrontLocale]) }}" method="post">
                @csrf
                <label for="email">{{ $copy['email'] }}</label>
                <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required>

                <button type="submit">{{ $copy['send_reset_link'] }}</button>
            </form>

            <p class="login-status" aria-live="polite">{{ $statusMessage }}</p>
            <p class="login-switch">
                <a href="{{ route('storefront.login', ['lang' => $storefrontLocale]) }}">{{ $copy['back_to_login'] }}</a>
            </p>
        </article>
    </section>
@endsection
