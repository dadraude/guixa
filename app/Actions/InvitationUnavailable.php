<?php

namespace App\Actions;

use RuntimeException;

/**
 * The invitation was already used, cancelled, expired or never existed.
 */
class InvitationUnavailable extends RuntimeException {}
