<li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
<li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
<li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Users</a></li>
<li class="breadcrumb-item @if($active ?? false) active @endif"><a
        href="{{ route('admin.users.show', $user->id) }}">{{ $user->nickname }}</a></li>

