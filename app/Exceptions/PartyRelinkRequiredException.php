<?php

namespace App\Exceptions;

use Exception;

class PartyRelinkRequiredException extends Exception implements ProviderRelinkRequiredException {}
