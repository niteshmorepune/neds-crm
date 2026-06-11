<?php

return [

    /*
     | When true, admins and managers who have not yet enabled two-factor are
     | redirected to their profile to set it up before using the app. The
     | post-login 2FA challenge (for users who HAVE enabled it) always applies
     | regardless of this flag. Turn this on in production.
     */
    'enforce_two_factor_enrollment' => env('ENFORCE_TWO_FACTOR_ENROLLMENT', false),

];
