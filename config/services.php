<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
     * Google: OAuth for connecting Gmail mailboxes, and the Pub/Sub topic that
     * Gmail pushes new-mail notifications to. See docs/gmail.md for the one-time
     * Google Cloud setup. With no topic configured, mailboxes fall back to the
     * scheduled poll.
     */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/settings/mailboxes/gmail/callback'),
        'pubsub_topic' => env('GOOGLE_PUBSUB_TOPIC'),
        'pubsub_audience' => env('GOOGLE_PUBSUB_AUDIENCE'),
        'pubsub_service_account' => env('GOOGLE_PUBSUB_SERVICE_ACCOUNT'),
    ],

    /*
     * When NGROK_DOMAIN is set, `php artisan dev` also starts an ngrok tunnel to php artisan serve.
     * See docs/gmail.md.
     */
    'ngrok' => [
        'domain' => env('NGROK_DOMAIN'),
        'port' => (int) env('NGROK_PORT', 8000),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
