<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dev Routes — loaded only in local/testing
|--------------------------------------------------------------------------
|
| These routes expose internal state (invitation tokens, reset codes, etc.)
| for development and testing convenience. They are NOT registered in any
| other environment — the file itself is never loaded, so requests to /dev/*
| return a real 404, not a 403 authorization error.
|
| See api/API_ARCHITECTURE.md §9 for the full convention.
|
| Each block that introduces an out-of-band code (invitation, password reset,
| MFA) adds its own endpoint here as part of its DoD. No endpoints are
| pre-built — the first one is GET /dev/invitations/last?email=... in AUTH-B01.
*/
