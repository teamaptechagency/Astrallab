<?php

namespace App\Support;

use App\Models\Setting;

/**
 * What the settings screen can change, and where each one lands.
 *
 * One list, used three ways: to build the form, to validate what comes back,
 * and to overlay the saved values onto config at boot. Adding a setting is one
 * entry here rather than an edit in four files, which is how a form ends up
 * with a field that saves nowhere.
 *
 * The config keys matter. Everything already reads config('astralab.…'), so a
 * saved setting simply replaces what .env put there. Nothing else in the
 * application needs to know that settings exist.
 */
class Settings
{
    /**
     * @return array<string, array{
     *     config: string,
     *     label: string,
     *     hint?: string,
     *     type?: string,
     *     group: string,
     *     rules: array<int, string>
     * }>
     */
    public static function fields(): array
    {
        return [
            'contact.email' => [
                'config' => 'astralab.contact.email',
                'label' => 'Email address',
                'hint' => 'Shown on the contact page and used for enquiries.',
                'type' => 'email',
                'group' => 'How people reach you',
                'rules' => ['nullable', 'email', 'max:190'],
            ],
            'contact.whatsapp' => [
                'config' => 'astralab.contact.whatsapp',
                'label' => 'WhatsApp number',
                // The commonest way to get this wrong, said before it is typed
                // rather than after the link fails to open.
                'hint' => 'Digits only, country code first, no plus: 8801XXXXXXXXX',
                'group' => 'How people reach you',
                'rules' => ['nullable', 'regex:/^[0-9]{8,15}$/'],
            ],
            'contact.phone' => [
                'config' => 'astralab.contact.phone',
                'label' => 'Phone number',
                'hint' => 'Written however you want it shown. +880 1X XXXX XXXX is fine.',
                'group' => 'How people reach you',
                'rules' => ['nullable', 'string', 'max:40'],
            ],
            'contact.hours' => [
                'config' => 'astralab.contact.hours',
                'label' => 'When you answer',
                'group' => 'How people reach you',
                'rules' => ['nullable', 'string', 'max:120'],
            ],
            'contact.address' => [
                'config' => 'astralab.contact.address',
                'label' => 'Address',
                'hint' => 'Shown on the contact page and in the footer.',
                'type' => 'textarea',
                'group' => 'How people reach you',
                'rules' => ['nullable', 'string', 'max:400'],
            ],

            'company.name' => [
                'config' => 'astralab.company.name',
                'label' => 'Company name',
                'hint' => 'Named on the terms, privacy and refund pages.',
                'group' => 'The business',
                'rules' => ['nullable', 'string', 'max:120'],
            ],
            'company.partner' => [
                'config' => 'astralab.company.partner',
                'label' => 'Partner name',
                'group' => 'The business',
                'rules' => ['nullable', 'string', 'max:120'],
            ],
            'company.trade_licence' => [
                'config' => 'astralab.company.trade_licence',
                'label' => 'Trade licence number',
                'hint' => 'Left off the terms page if blank.',
                'group' => 'The business',
                'rules' => ['nullable', 'string', 'max:60'],
            ],
            'refund_days' => [
                'config' => 'astralab.refund_days',
                'label' => 'Refund window, in days',
                'hint' => 'Stated on the refund page and the buying page, from this one number.',
                'type' => 'number',
                'group' => 'The business',
                'rules' => ['nullable', 'integer', 'min:0', 'max:365'],
            ],

            /*
             * The shop's link to the hub.
             *
             * Here rather than in .env because .env means a file manager, and a
             * file manager means "save as .env.txt" and an afternoon. These two
             * are the whole connection: where to ask, and what to say when
             * asking.
             */
            'hub_url' => [
                'config' => 'astralab.hub_url',
                'label' => 'Hub address',
                'hint' => 'Where orders are sent and licences are issued. https://manage.astrallabs.uk',
                'group' => 'The licence hub',
                'rules' => ['nullable', 'url', 'max:190'],
            ],
            'store_secret' => [
                'config' => 'astralab.store_secret',
                'label' => 'Store secret',
                'hint' => 'From the hub — Settings → API config, or the STORE_API_SECRET line in its .env. '
                    .'It must match exactly, or every order is refused and paying customers receive nothing.',
                // Never rendered back into the page, and an empty box means
                // "leave it alone" rather than "erase it". A secret shown on a
                // screen is a secret in a screenshot.
                'secret' => true,
                'group' => 'The licence hub',
                'rules' => ['nullable', 'string', 'max:190'],
            ],
        ];
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::fields() as $key => $field) {
            $groups[$field['group']][$key] = $field;
        }

        return $groups;
    }

    /**
     * Put the saved values over the ones .env supplied.
     *
     * Called at boot. A setting that has never been saved leaves the .env value
     * alone, so an install that was configured by hand keeps working exactly as
     * it did until somebody uses the screen.
     */
    public static function apply(): void
    {
        foreach (self::fields() as $key => $field) {
            $saved = Setting::get($key);

            if ($saved !== null) {
                config([$field['config'] => $saved]);
            }
        }
    }

    /** @return array<string, string|null> */
    public static function current(): array
    {
        $values = [];

        foreach (self::fields() as $key => $field) {
            // A secret is never handed back to the page. The form shows an
            // empty box and says whether one is set — enough to answer "is it
            // configured?" without putting the value in a screenshot, a
            // browser cache, or over anybody's shoulder.
            if ($field['secret'] ?? false) {
                $values[$key] = '';

                continue;
            }

            // The saved value if there is one, otherwise whatever the page is
            // actually using — so the form shows the truth rather than blanks
            // beside a site that plainly has a company name on it.
            $values[$key] = Setting::get($key) ?? (string) config($field['config']);
        }

        return $values;
    }

    /** Which settings hold a secret, and whether each one has been given a value. */
    public static function secretsSet(): array
    {
        $set = [];

        foreach (self::fields() as $key => $field) {
            if ($field['secret'] ?? false) {
                $set[$key] = (Setting::get($key) ?? (string) config($field['config'])) !== '';
            }
        }

        return $set;
    }
}
