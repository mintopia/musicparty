@extends('layouts.application')

@section('content')
    <div class="page-header">
        <h1>Games</h1>
        @can('create', \App\Models\Game::class)
            <a class="btn ml-auto btn-outline-success" href="{{ route('games.create') }}">
                <span class="fa fa-plus"></span>
                Add Game
            </a>
        @endcan
    </div>

    <div class="row row-cards row-deck">
        @foreach ($games as $game)
            <div class="col-md-4">
                <div class="card">
                    <img class="card-img-top" src="https://placekitten.com/1280/720" />
                    <div class="card-header">
                        <h3 class="card-title"><a href="{{ route('games.show', $game) }}">{{ $game->name }}</a></h3>
                    </div>
                    <div class="card-body pt-3">
                        <h4 class="mt-0">
                            <span class="badge bg-gray">
                                <span class="fa fa-user"></span>
                                {{ $game->max_players }} Players
                            </span>
                            <span class="badge bg-gray">
                                <span class="fa fa-clock-o"></span>
                                4 Hours
                            </span>
                        </h4>
                        @if ($game->description)
                            <x-markdown>
                                {{ $game->description }}
                            </x-markdown>
                        @endif
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('games.show', $game) }}" class="btn btn-block btn-success">More Details</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>


    @if ($games->total() > $games->count() )
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    {{ $games->links() }} }}
                </div>
            </div>
        </div>
    @endif
@endsection
