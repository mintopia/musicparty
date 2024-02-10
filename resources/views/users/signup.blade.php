@extends('layouts.login')

@section('content')

    <h2 class="h3 text-center mb-3">
        Create Account
    </h2>

    <div class="mb-3 text-center">
        <span class="avatar avatar-xl rounded" style="background-image: url('{{ $user->avatarUrl() }}')"></span>
    </div>

    <form action="{{ route('login.signup') }}" method="post">
        {{ csrf_field() }}
        {{ method_field('PUT') }}
        <div class="mb-3">
            <label class="form-label required">Nickname</label>
            <div>
                <input type="text" name="nickname" class="form-control @error('nickname') is-invalid @enderror"
                       placeholder="Nickname" value="{{ old('nickname', $user->nickname ?? '') }}">
                <small class="form-hint">Your preferred nickname</small>
                @error('nickname')
                <p class="invalid-feedback">{{ $message }}</p>
                @enderror
            </div>
        </div>
        @if($terms || $privacy)
            <div class="mb-5">
                <div>
                    <label class="form-check @error('terms') is-invalid @enderror">
                        <input class="form-check-input" name="terms" value="1" type="checkbox">
                        <span class="form-check-label required">
                            I agree to the
                            @if($terms)
                                <a href="{{ $terms }}">Terms and Conditions</a>
                                @if($privacy)
                                    and
                                @endif
                            @endif
                            @if($privacy)
                                <a href="{{ $privacy }}">Privacy Policy</a>
                            @endif
                        </span>
                    </label>
                    @error('terms')
                    <p class="invalid-feedback">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        @endif
        <div class="d-flex">
            <a href="{{ route('logout') }}" class="btn btn-link">Cancel</a>
            <button type="submit" class="btn btn-primary ms-auto">Continue</button>
        </div>
    </form>
@endsection
