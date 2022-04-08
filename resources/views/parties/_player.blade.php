<player
    name="{{ $party->name }}"
    code="{{ $party->code }}"
    canmanage="{{ $party->user_id == Auth::user()->id ? true : false }}"
    initialstate='@json($party->getState(Auth::user()))'>
</player>
