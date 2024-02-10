@extends('layouts.app', [
    'activenav' => 'admin',
])

@section('breadcrumbs')
    @include('admin.themes._breadcrumbs')
    <li class="breadcrumb-item active"><a href="{{ route('admin.settings.themes.edit', $theme->id) }}">Edit Theme</a></li>
@endsection

@section('content')
    <div class="page-header mt-0">
        <h1>Edit {{ $theme->name }}</h1>
    </div>

    <div class="col-md-8 offset-md-2">
        <form action="{{ route('admin.settings.themes.update', $theme->id) }}" method="post" class="card">
            {{ csrf_field() }}
            {{ method_field('PATCH') }}
            @if ($theme->readonly)
                <div class="alert alert-info alert-important m-4">
                    It is not possible to edit this theme, you can only set it to be active and change dark mode.
                </div>
            @endif
            @include('admin.themes._form')
            <div class="card-footer text-end">
                <div class="d-flex">
                    <a href="{{ route('admin.settings.index') }}" class="btn btn-link">Cancel</a>
                    <button type="submit" class="btn btn-primary ms-auto">Save</button>
                </div>
            </div>
        </form>
    </div>
@endsection
