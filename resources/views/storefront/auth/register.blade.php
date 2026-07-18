@extends('layouts.storefront')

@section('content')
    <section class="login-page">
        <article class="login-card reveal in-view" aria-labelledby="register-title">
            <p class="login-kicker">{{ $copy['kicker'] }}</p>
            <h1 id="register-title" class="login-title">{{ $copy['register_title'] }}</h1>
            <p class="login-caption">{{ $copy['register_caption'] }}</p>

            <form class="login-form" action="{{ route('storefront.register.submit', ['lang' => $storefrontLocale]) }}" method="post">
                @csrf
                <input type="hidden" name="next" value="{{ $nextPath }}">
                <label for="display_name">{{ $copy['display_name'] }}</label>
                <input id="display_name" name="display_name" type="text" autoComplete="name" value="{{ old('display_name') }}" required />

                <label for="email">{{ $copy['email'] }}</label>
                <input id="email" name="email" type="email" autoComplete="email" value="{{ old('email') }}" required />

                <label for="password">{{ $copy['password'] }}</label>
                <input id="password" name="password" type="password" autoComplete="new-password" required />

                <label for="confirm_password">{{ $copy['confirm_password'] }}</label>
                <input id="confirm_password" name="confirm_password" type="password" autoComplete="new-password" required />

                <button type="submit">{{ $copy['create_account'] }}</button>
            </form>

            <p class="login-status" aria-live="polite">{{ $statusMessage }}</p>
            <p class="login-switch">
                {{ $copy['have_account'] }} <a href="{{ route('storefront.login', ['next' => $nextPath, 'lang' => $storefrontLocale]) }}">{{ $copy['switch_sign_in'] }}</a>
            </p>
        </article>
    </section>
@endsection
