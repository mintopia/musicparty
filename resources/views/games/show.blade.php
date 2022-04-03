@extends('layouts.application')

@section('content')
    <div class="page-header">
        <h1>{{ $game->name }}</h1>
        @can('update', $game)
            <a class="btn ml-auto btn-outline-secondary" href="{{ route('games.edit', $game) }}">
                <span class="fa fa-pencil"></span>
                Edit
            </a>
        @endcan
    </div>


    @if($game->description)
        <x-markdown>{{ $game->description }}</x-markdown>
    @endif

    <h2>Players</h2>
@endsection
