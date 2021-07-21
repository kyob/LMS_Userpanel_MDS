<?php

$USERPANEL->AddModule(
        trans('My device settings'), // Display name
        'mydevicesettings', // Module name - must be the same as directory name
        trans('Allow client to management CPE.'), // Tip
        40, // Priority
        trans('Access to selected CPE configuration options for the client.') // Description
);
