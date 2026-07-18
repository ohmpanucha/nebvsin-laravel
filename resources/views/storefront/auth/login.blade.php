@extends('layouts.storefront')

@section('content')
    <section class="login-page">
        <article class="login-card reveal in-view" aria-labelledby="login-title">
            <p class="login-kicker">{{ $copy['kicker'] }}</p>
            <h1 id="login-title" class="login-title">{{ $copy['login_title'] }}</h1>
            <p class="login-caption">{{ $copy['login_caption'] }}</p>

            <form class="login-form" action="{{ route('storefront.login.submit', ['lang' => $storefrontLocale]) }}" method="post">
                @csrf
                <input type="hidden" name="next" value="{{ $nextPath }}">
                <label for="email">{{ $copy['email'] }}</label>
                <input id="email" name="email" type="email" autoComplete="email" value="{{ old('email') }}" required />

                <label for="password">{{ $copy['password'] }}</label>
                <input id="password" name="password" type="password" autoComplete="current-password" required />

                <p class="login-form-link">
                    <a href="{{ route('storefront.password.request', ['lang' => $storefrontLocale]) }}">{{ $copy['forgot_password_link'] }}</a>
                </p>

                <button type="submit">{{ $copy['sign_in'] }}</button>
            </form>

            <p class="login-status" aria-live="polite">{{ $statusMessage }}</p>
            <p class="login-switch">
                {{ $copy['no_account'] }} <a href="{{ route('storefront.register', ['next' => $nextPath, 'lang' => $storefrontLocale]) }}">{{ $copy['create_one'] }}</a>
            </p>
        </article>
    </section>
@endsection
