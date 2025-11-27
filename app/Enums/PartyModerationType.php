<?php

namespace App\Enums;

enum PartyModerationType
{
    case mtName;
    case mtArtist;
    case mtArtistId;
    case mtAlbum;
    case mtAlbumId;
    case mtId;
    case mtISRC;
}
