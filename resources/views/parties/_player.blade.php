<player
    name="{{ $party->name }}"
    code="{{ $party->code }}"
    canmanage="{{ $party->isAdmin(Auth::user()) ? true : false }}"
    initialstate='@json($party->getState(Auth::user()))'>
</player>
