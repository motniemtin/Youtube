<?php

return [

    /**
     * Client ID.
     */

    /**
     * Scopes.
     */
    'scopes' => [
        'https://www.googleapis.com/auth/youtube',
        'https://www.googleapis.com/auth/youtube.upload',
        'https://www.googleapis.com/auth/youtube.readonly'
    ],

    /**
     * Route URI's
     */
    'routes' => [

        /** 
         * Determine if the Routes should be disabled.
         * Note: We recommend this to be set to "false" immediately after authentication.
         */
        'enabled' => true,

    ]

];
