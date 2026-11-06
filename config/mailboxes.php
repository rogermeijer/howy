<?php

return [

    /*
    | Domains anyone can have an address on. For a mailbox on one of these,
    | "the same domain" would mean everyone, so its senders are listed one by
    | one, and none of these is ever a rule of its own.
    */
    'public_domains' => [
        'gmail.com', 'googlemail.com',
        'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
        'yahoo.com', 'aol.com',
        'icloud.com', 'me.com', 'mac.com',
        'proton.me', 'protonmail.com',
        'gmx.com', 'gmx.net',
        'ziggo.nl', 'kpnmail.nl', 'hetnet.nl', 'planet.nl', 'home.nl', 'xs4all.nl', 'telfort.nl', 'upcmail.nl',
    ],

];
