@extends('layouts.app', [
    'activenav' => 'profile',
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('user.profile') }}">Profile</a></li>
    <li class="breadcrumb-item active"><a href="{{ route('user.profile.edit') }}">Edit Profile</a></li>
@endsection

@section('content')
    <div class="page-header mt-0">
        <h1>Edit Profile</h1>
    </div>

    <div class="col-md-6 offset-md-3">
        <form action="{{ route('user.profile.update') }}" method="post" class="card">
            {{ csrf_field() }}
            {{ method_field('PATCH') }}
            <div class="card-body">
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
            </div>
            <div class="card-footer text-end">
                <div class="d-flex">
                    <a href="{{ route('user.profile') }}" class="btn btn-link">Cancel</a>
                    <button type="submit" class="btn btn-primary ms-auto">Save</button>
                </div>
            </div>
        </form>
    </div>
@endsection
