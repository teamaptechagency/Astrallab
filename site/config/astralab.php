<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Who to contact
    |--------------------------------------------------------------------------
    |
    | The contact page, the footer and the legal pages all read from here, so
    | changing a phone number is one line in .env rather than a search across
    | four templates — which is how a site ends up publishing a number that was
    | disconnected last year.
    |
    | Anything left blank is simply not shown. A contact page listing an empty
    | address is worse than one listing two ways to reach a person.
    |
    */

    'contact' => [
        'email' => env('ASTRALAB_EMAIL', ''),
        'phone' => env('ASTRALAB_PHONE', ''),
        // Digits only, with the country code and no +, which is the form
        // wa.me links take: 8801XXXXXXXXX.
        'whatsapp' => env('ASTRALAB_WHATSAPP', ''),
        'address' => env('ASTRALAB_ADDRESS', ''),
        'hours' => env('ASTRALAB_HOURS', 'Saturday to Thursday, 10am – 7pm'),
    ],

    /*
    |--------------------------------------------------------------------------
    | The legal entity
    |--------------------------------------------------------------------------
    |
    | Named on the terms, privacy and refund pages. These pages state what the
    | software and this site actually do — they are not a template downloaded
    | from somewhere, and they should not be replaced with one without reading
    | what they currently say.
    |
    */

    'company' => [
        'name' => env('ASTRALAB_COMPANY', 'Astra Lab'),
        'partner' => env('ASTRALAB_PARTNER', 'AP Tech Agency'),
        'trade_licence' => env('ASTRALAB_TRADE_LICENCE', ''),
    ],

    /*
    | How long after purchase a refund can be asked for, in days. Stated on the
    | refund page and on the pricing section, from one number, so the two can
    | never disagree — which is the sort of contradiction a customer screenshots.
    */
    'refund_days' => (int) env('ASTRALAB_REFUND_DAYS', 7),

];
