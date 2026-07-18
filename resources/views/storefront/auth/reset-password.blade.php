@extends('layouts.storefront')

@section('content')
    <section class="login-page">
        <article class="login-card reveal in-view" aria-labelledby="reset-password-title">
            <p class="login-kicker">{{ $copy['kicker'] }}</p>
            <h1 id="reset-password-title" class="login-title">{{ $copy['reset_password_title'] }}</h1>
            <p class="login-caption">{{ $copy['reset_password_caption'] }}</p>

            <form class="login-form" action="{{ route('storefront.password.update', ['lang' => $storefrontLocale]) }}" method="post">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <label for="email">{{ $copy['email'] }}</label>
                <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email', $email) }}" required>

                <label for="password">{{ $copy['new_password'] }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>

                <label for="confirm_password">{{ $copy['confirm_password'] }}</label>
                <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" required>

                <button type="submit">{{ $copy['reset_password_action'] }}</button>
            </form>

            <p class="login-status" aria-live="polite">{{ $statusMessage }}</p>
        </article>
    </section>
@endsection
