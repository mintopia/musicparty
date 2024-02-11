<player
    code="{{ $party->code }}"
    initialstate='@json($party->getState())'
    can_manage="{{ $canManage }}"
    can_downvote="{{ $party->downvotes }}"
>
</player>
