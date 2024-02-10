@extends('layouts.app', [
    'activenav' => 'home',
    'hidetopnav' => true,
])

@push('precontainer')
    @include('parties._player')
@endpush

@section('content')

    <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
        <h3>Up Next</h3>

        <div class="card mb-3">
            <div class="list-group card-list-group">
                <div class="list-group-item">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <img src="https://i.scdn.co/image/ab67616d0000b27344036a5c0d16b6ed97d28519" class="rounded" alt="Górą ty" width="40" height="40">
                        </div>
                        <div class="col">
                            Aerith's Theme (Final Fantasy VII)
                            <div class="text-secondary">
                                Nobuo Uematsu
                            </div>
                        </div>
                        <div class="col-auto text-secondary">5:48</div>
                    </div>
                </div>
            </div>
        </div>
        <h3>Queued</h3>
        <div class="card">
            <div class="list-group card-list-group">
                <div class="list-group-item">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <img src="https://i.scdn.co/image/ab67616d000048514f3bfab621b4f635e201fab3" class="rounded" alt="Górą ty" width="40" height="40">
                        </div>
                        <div class="col">
                            Civilization IV Medley
                            <div class="text-secondary">
                                Video Games Live
                            </div>
                        </div>
                        <div class="col-auto text-secondary">4:55</div>
                        <div class="col-auto text-center">
                            <div class="row">
                                <div class="col-4">
                                    <i class="icon ti ti-arrow-big-up-filled"></i>
                                </div>
                                <div class="col-4">
                                    10
                                </div>
                                <div class="col-4">
                                    <i class="icon ti ti-arrow-big-down"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto lh-1">
                            <div class="dropdown">
                                <a href="#" class="link-secondary link-underline-opacity-0" data-bs-toggle="dropdown">
                                    <i class="icon ti ti-dots-vertical"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="#">
                                        Ban...
                                    </a>
                                    <a class="dropdown-item" href="#">
                                        Remove
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="list-group-item pl-3 pr-0">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <img src="https://i.scdn.co/image/ab67616d0000b2733479a61eaae6d85a0308fe1a" class="rounded" alt="Górą ty" width="40" height="40">
                        </div>
                        <div class="col">
                            Main Theme of Final Fantasy VII
                            <div class="text-secondary">
                                Nobuo Uematsu
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="col-auto text-secondary">6:43</div>
                        </div>
                        <div class="col-auto text-center">
                            <div class="row">
                                <div class="col-4">
                                    <i class="icon ti ti-arrow-big-up-filled"></i>
                                </div>
                                <div class="col-4">
                                    10
                                </div>
                                <div class="col-4">
                                    <i class="icon ti ti-arrow-big-down"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto lh-1">
                            <div class="dropdown">
                                <a href="#" class="link-secondary link-underline-opacity-0" data-bs-toggle="dropdown">
                                    <i class="icon ti ti-dots-vertical"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="#">
                                        Ban...
                                    </a>
                                    <a class="dropdown-item" href="#">
                                        Remove
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
