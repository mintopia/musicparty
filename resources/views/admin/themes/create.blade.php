@extends('layouts.app', [
    'activenav' => 'admin',
])

@section('breadcrumbs')
    @include('admin.themes._breadcrumbs')
    <li class="breadcrumb-item active"><a href="{{ route('admin.settings.themes.create') }}">Create Theme</a>
        @endsection

        @section('content')
            <div class="page-header mt-0">
                <h1>Create Theme</h1>
            </div>

            <div class="col-md-8 offset-md-2">
                <form action="{{ route('admin.settings.themes.store') }}" method="post" class="card">
                    {{ csrf_field() }}
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
