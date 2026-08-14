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


    /*
    |--------------------------------------------------------------------------
    | This build's version
    |--------------------------------------------------------------------------
    |
    | Shown on the Updates screen and written into every archive the packager
    | makes, so "which build is this site running?" has an answer that does not
    | involve comparing file dates — which updating rewrites by definition.
    |
    | Bumped here by hand, because only a person knows whether a change was a
    | fix or a feature.
    */
    'version' => env('ASTRALAB_VERSION', '1.0.2'),

    /*
    |--------------------------------------------------------------------------
    | The hub
    |--------------------------------------------------------------------------
    |
    | manage.astrallabs.uk. This site sells; the hub decides that somebody has
    | paid, and issues the licence.
    |
    | The split matters more than it looks. This application is on the public
    | internet with a shop on it. If it could mint licences, anybody who got
    | into it could mint licences — so it cannot. It holds a secret that lets it
    | ask, and nothing that lets it decide.
    */
    'hub_url' => env('ASTRALAB_HUB_URL', 'https://manage.astrallabs.uk'),

    /*
    | Shared with the hub, which compares it in constant time. It authorises
    | placing an order and reading one back, and nothing else. Losing it does
    | not let anybody issue a licence — it lets them place orders nobody paid
    | for, which a human then declines.
    */
    'store_secret' => env('STORE_API_SECRET', ''),


    /*
    |--------------------------------------------------------------------------
    | Installed marker
    |--------------------------------------------------------------------------
    |
    | Written by the installer when the site is finished, and read on every
    | request to decide whether to send a visitor to the wizard. A file rather
    | than a setting, because before the database exists there is nothing to
    | ask — and this check has to answer without a connection.
    |
    | Overridable so the test suite can point it somewhere disposable.
    */
    "install_lock" => env("ASTRALAB_INSTALL_LOCK", storage_path("app/installed.json")),


    /*
    |--------------------------------------------------------------------------
    | Response signing key
    |--------------------------------------------------------------------------
    |
    | The Ed25519 PRIVATE key this hub signs replies with. Every copy of the CMS
    | carries the matching PUBLIC key and refuses anything it cannot verify
    | against it, which is what stops a hostile DNS answer telling an install it
    | is licensed.
    |
    | This never leaves the server. Shipping it would let any customer forge
    | "your licence is valid" for everybody.
    |
    | Stored as one line with literal 
, which is the only way a multi-line PEM
    | survives a .env file.
    */
    "signing_key" => env("SIGNING_PRIVATE_KEY", ""),


    /*
    |--------------------------------------------------------------------------
    | The back-office hostname
    |--------------------------------------------------------------------------
    |
    | manage.astrallabs.uk. Blank while its DNS is being arranged, because
    | restricting the console to a hostname that does not resolve yet would lock
    | everybody out of it.
    |
    | Once set, the console answers there and nowhere else — the public domain
    | is left serving only public things. One application, two addresses: one
    | for buyers, one for the back office and for the shops calling home.
    */
    "manage_host" => env("ASTRALAB_MANAGE_HOST", ""),

];