<?php

namespace App\Http\Controllers;

/**
 * Terms, privacy and refunds.
 *
 * Written from what the software actually does rather than adapted from a
 * template found online: the licence really is one domain and really can be
 * moved, the hub really does receive a short, listable set of fields, and shop
 * data really does stay on the customer's own server. A page that promises
 * something the code does not do is worse than no page, because somebody will
 * eventually hold us to it.
 *
 * Content lives here rather than in three Blade files so the facts that appear
 * on more than one of them — the refund window, the company name — come from
 * one place and cannot drift apart.
 */
class LegalController extends Controller
{
    public function terms()
    {
        $company = config('astralab.company.name');

        return view('pages.legal', [
            'eyebrow' => 'Legal',
            'heading' => 'Terms of use',
            'summary' => 'What you are buying, what we promise, and what we do not.',
            'sections' => [
                [
                    'title' => 'What you are buying',
                    'body' => [
                        'A licence to install and run our software on hosting you control. One payment, no recurring fee. The software is yours to keep and to run for as long as you like.',
                        'You are not buying the copyright. You may not resell the software, redistribute it, or use one licence to run shops for other people.',
                    ],
                ],
                [
                    'title' => 'One licence, one domain',
                    'body' => [
                        'Each licence activates on a single domain at a time. Moving to a different domain is free and takes minutes: deactivate in your admin panel, then enter the same key on the new domain.',
                        'Running the same licence on two live domains at once is not permitted, and activation will refuse it.',
                    ],
                ],
                [
                    'title' => 'Updates and support',
                    'body' => [
                        'Updates are included for as long as we publish them, at no extra charge. Your shop checks for them and you choose when to apply them — we never change your site without you.',
                        'Support means helping you use software that works. It does not include writing custom features, designing your shop, or fixing problems caused by your hosting, other plugins, or changes you made to the code.',
                    ],
                ],
                [
                    'title' => 'Your shop is yours',
                    'body' => [
                        'Your products, orders and customers live in your database on your hosting. We do not hold them and cannot see them. If you stop dealing with us entirely, your shop keeps running.',
                        'That also means backups are yours. We strongly recommend using your host\'s daily backups, or a care plan that includes them.',
                    ],
                ],
                [
                    'title' => 'What we are not responsible for',
                    'body' => [
                        'We cannot be held liable for lost sales, lost data, or business losses arising from use of the software. Our liability in any circumstance is limited to what you paid us.',
                        'Payment gateways, hosting and delivery companies are separate businesses with their own terms. Problems with them are between you and them, though we will help you work out what is happening.',
                    ],
                ],
                [
                    'title' => 'Ending it',
                    'body' => [
                        'We may suspend a licence used to break these terms — reselling it, or running it on domains that are not yours. We will tell you why first, in writing, and give you a chance to put it right.',
                        'These terms are governed by the laws of Bangladesh.',
                    ],
                ],
                [
                    'title' => 'Who we are',
                    'body' => array_values(array_filter([
                        $company.', in partnership with '.config('astralab.company.partner').'.',
                        config('astralab.company.trade_licence')
                            ? 'Trade licence '.config('astralab.company.trade_licence').'.'
                            : null,
                        config('astralab.contact.address') ?: null,
                    ])),
                ],
            ],
        ]);
    }

    public function privacy()
    {
        return view('pages.legal', [
            'eyebrow' => 'Legal',
            'heading' => 'Privacy',
            'summary' => 'What we collect, which is less than you would expect, and why.',
            'sections' => [
                [
                    'title' => 'Your customers\' data never reaches us',
                    'body' => [
                        'This is the part most people want to know. Your shop runs on your hosting, and the orders, addresses and phone numbers your customers give you are stored in your database. They are never sent to us. We could not produce them if asked.',
                        'That is a consequence of how the software is built, not a policy we could quietly change.',
                    ],
                ],
                [
                    'title' => 'What your shop does send us',
                    'body' => [
                        'To check your licence is valid and to tell you when an update is ready, your shop contacts us periodically. That request contains: your licence key, your domain, which product it is, the version installed, and your PHP version. Nothing else.',
                        'We keep that so we can tell you which of your sites is on which version, and so a suspended or refunded licence stops working.',
                    ],
                ],
                [
                    'title' => 'When you report a problem',
                    'body' => [
                        'Using <strong>Report a problem</strong> in your admin panel sends us what you wrote, plus your version, PHP version and domain. It does not send your database, your products or your customers.',
                        'We keep reports so we can fix what they describe and check the fix reached you.',
                    ],
                ],
                [
                    'title' => 'This website',
                    'body' => [
                        'This site sets no advertising or tracking cookies. If you sign in to buy, a session cookie keeps you signed in; that is all it does.',
                        'If you contact us, we keep the conversation so the next person you speak to does not ask you everything again.',
                    ],
                ],
                [
                    'title' => 'Payments',
                    'body' => [
                        'Payments to us go through bKash, Nagad or a card processor. They handle the payment details — we never see your card number or your bKash PIN. We see that a payment succeeded, and for how much.',
                    ],
                ],
                [
                    'title' => 'Asking us to delete something',
                    'body' => [
                        'Write to us and we will remove what we hold about you, except records we are required to keep for tax and accounting. Note that deleting a licence record deactivates the software it unlocked.',
                    ],
                ],
            ],
        ]);
    }

    public function refund()
    {
        $days = config('astralab.refund_days');

        return view('pages.legal', [
            'eyebrow' => 'Legal',
            'heading' => 'Refunds',
            'summary' => 'Software is hard to un-sell, so the rule is simple and it favours you being sure before you buy.',
            'sections' => [
                [
                    'title' => 'The '.$days.'-day window',
                    'body' => [
                        'If the software does not work on your hosting and we cannot make it work, ask within '.$days.' days of purchase and we will refund you in full.',
                        'Refunds go back to the account you paid from, and take as long as bKash, Nagad or your bank takes — usually a few working days.',
                    ],
                ],
                [
                    'title' => 'What we will do first',
                    'body' => [
                        'Nearly every "it does not work" turns out to be a hosting setting — an old PHP version, or outgoing connections blocked. Give us a chance to look before asking for the money back. Most of these take one message to fix.',
                        'If your hosting genuinely cannot run it, that is a refund, not an argument. The requirements are published on the <a href="'.route('docs').'#requirements">install guide</a> precisely so this can be checked before you pay.',
                    ],
                ],
                [
                    'title' => 'When we will not refund',
                    'body' => [
                        'After '.$days.' days. After the licence has been used to run a live shop taking real orders. Or where the request is that you changed your mind about the design, which is visible on this site before you buy.',
                        'Setup, product upload and SEO work are refundable only before we start. Once the work is done it cannot be returned.',
                    ],
                ],
                [
                    'title' => 'Care plans',
                    'body' => [
                        'Monthly plans can be cancelled at any time and stop at the end of the month you have paid for. Six-month and yearly plans are discounted in exchange for the commitment, and are refunded pro-rata only where we have failed to do the work.',
                        'Cancelling a plan never affects your shop. You own the software either way.',
                    ],
                ],
            ],
        ]);
    }
}
