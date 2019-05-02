<?php

return [

    /*
     |--------------------------------------------------------------------------
     | Environment setting
     |--------------------------------------------------------------------------
     |
     | Communication to the Eyewitness.io API is enabled by default. You can
     | override the value by setting to false, which might be useful for your
     | development environment so you do not trigger false alerts when
     | developing etc.
     |
     | You should set this to false in your development .env file
     |
     */

    'api_enabled' => env('EYEWITNESS_API_ENABLED', true),


    /*
     |--------------------------------------------------------------------------
     | Application token & secret key
     |--------------------------------------------------------------------------
     |
     | Your unique Eyewitness.io application token & secret key. These settings
     | will be automatically set by the installer when you first run
     | 'artisan eyewitness:install'.
     |
     | WARNING: if you change either of these settings, you monitors will not
     | work correctly. Please contact us if you have any questions or need
     | help with your setup: 'support@eyewitness.io'
     |
     */

    'app_token' => env('EYEWITNESS_APP_TOKEN', 'APP_TOKEN_PLACEHOLDER'),
    'secret_key' => env('EYEWITNESS_SECRET_KEY', 'SECRET_KEY_PLACEHOLDER'),


    /*
     |--------------------------------------------------------------------------
     | Configure monitoring
     |--------------------------------------------------------------------------
     |
     | You can turn off certain parts of the monitoring. For example, if your
     | application does not use email, you should turn that off.
     |
     */

    'monitor_email' => true,
    'monitor_queue' => true,
    'monitor_scheduler' => true,
    'monitor_database' => true,
    'monitor_disk' => true,
    'monitor_request' => true,
    'monitor_log' => true,
    'monitor_composer_lock' => true,
    'monitor_maintenance_mode' => true,


    /*
     |--------------------------------------------------------------------------
     | Queue tube monitoring list
     |--------------------------------------------------------------------------
     |
     | If you run multiple queue tubes, please list them below for monitoring.
     | A sensible default has been configured for you as part of the installation
     | process that should work for most common applications.
     |
     | Below is a more advanced example of how you can monitor multiple queue
     | connections and tubes.
     |
     | 'queue_tube_list' => ['connection1' => ['tube1', 'tube2'],
     |                       'connection2' => ['tube1', 'tube2', 'tube3']],
     |
     | Not sure the best option for your application? Send us a quick email
     | at 'support@eyewitness.io' and we'll be happy to help.
     |
     */

    'queue_tube_list' => ['QUEUE_CONNECTION_PLACEHOLDER' => ['QUEUE_TUBE_PLACEHOLDER']],


    /*
     |--------------------------------------------------------------------------
     | Database status monitoring list
     |--------------------------------------------------------------------------
     |
     | If your application uses multiple databases, then you can list them below
     | and each will be monitored to ensure they are online and available.
     |
     | The array should contain names corresponding to one of the connections
     | listed in your "config/database.php" configuration file. Below is an
     | example of how you can monitor multiple databases.
     |
     | 'database_connections' => ['mysql_main', 'other_database'],
     |
     | Note an null value will simply use the "default" connection - which is
     | sufficient for most applications.
     |
     */

    'database_connections' => null,


    /*
     |--------------------------------------------------------------------------
     | Database replication monitoring list
     |--------------------------------------------------------------------------
     |
     | If your application uses a replication solution (i.e. master/slave),
     | then you can choose for Eyewitness to monitor this.
     |
     | If you set this to true, you must run a database migration. We will
     | create a single table to use and will handle everything else automtically.
     |
     | If you enable this option - you must then run a migration. If you are on
     | Laravel 5.3 or below, you also need to firstly publish the migration:
     |
     | php artisan vendor:publish --tag=eyewitness
     |
     */

    'monitor_database_replication' => false,


    /*
     |--------------------------------------------------------------------------
     | Composer.lock location
     |--------------------------------------------------------------------------
     |
     | If you have enabled 'monitor_composer_lock' - then a daily check of
     | your composer.lock file will occur against the Symfony Security
     | check at https://security.symfony.com/
     |
     | The below is the location of your composer.lock file. You only need to
     | modify this config if your lock file is in a different location than
     | the default location.
     |
     */

    'composer_lock_file_location' => base_path('composer.lock'),


    /*
     |--------------------------------------------------------------------------
     | Queued emails
     |--------------------------------------------------------------------------
     |
     | When monitoring emails - the default is to send the test email immediately
     | and bypass the email queue. This is because if your application queues
     | a large amount of emails and process them over a long period of time,
     | the 'test' email will not be sent for a while, and that makes it difficult
     | to know if the emails are actually working or not.
     |
     | Because Eyewitness.io is already monitoring your queues seperately - there
     | is often no need for the test email to go via the queue, so it is set
     | to false by default.
     |
     | But depending on your application and specific circumstances - you might
     | want to force the email to still go via the queue - so we provide that
     | option.
     |
     */

    'send_queued_emails' => false,


    /*
     |--------------------------------------------------------------------------
     | Email frequency
     |--------------------------------------------------------------------------
     |
     | This determines how often your application will send a test email. The
     | default is once every 60 minutes.
     |
     | The eyewitness server will automatically detect this value and alert you
     | if we dont receive emails in the relatively appropriate timeframe.
     |
     | The shorter the timeframe the earlier you will know about an email problem,
     | but the more emails your system will send (and use up your email provider
     | quotas).
     |
     | Valid choices are: 15, 30, 60, 90, 120 or 180 (any other value will be
     | ignored by the server).
     |
     */

    'email_frequency' => 60,


    /*
     |--------------------------------------------------------------------------
     | Capture cron scheduler output
     |--------------------------------------------------------------------------
     |
     | If your cron schedulers are being monitored, then as part of that you can
     | have the output from the cron job captured and sent to Eyewitness - allowing
     | you to view it online at a later stage if needed.
     |
     | If you set this to false, the output is never captured or ever sent to
     | the Eyewitness server.
     |
     */

    'capture_cron_output' => true,


    /*
     |--------------------------------------------------------------------------
     | Privacy and security
     |--------------------------------------------------------------------------
     |
     | For some applications and systems, you might want to limit the amount
     | of data that can ever leave your server for security purposes.
     |
     | Note: most of the data transfer occurs via ajax between your browser
     | and your server and never touches the Eyewitness server anyway.
     |
     | But in some instances you can configure the pacakge to never send the
     | data to your own browser.
     |
     */

    'allow_failed_job_exception_data' => true,
    'allow_failed_job_payload_data' => true,


    /*
     |--------------------------------------------------------------------------
     | API routes
     |--------------------------------------------------------------------------
     |
     | Eyewitness.io allows you to view your log, scheduler and queue information
     | from the Eyewitness.io website. This occurs via routing, so no information
     | is actually stored on the Eyewitness.io server.
     |
     | These routes are protected by your secret key - so they are not accessible
     | by unauthorized people.
     |
     | You can choose to turn these routes off if you do not use them, but this
     | will reduce your ability to manage and control these features from
     | your Eyewitness.io dashboard.
     |
     */

    'routes_queue' => true,
    'routes_log' => true,
    'routes_scheduler' => true,


    /*
     |--------------------------------------------------------------------------
     | Eyewitness.io API URL
     |--------------------------------------------------------------------------
     |
     | This is the URL for the Eyewitness API servers. This should be left as
     | the default unless you are asked to change it by our support team.
     |
     */

    'api_url' => env('EYEWITNESS_API_URL', 'https://eyew.io/api/v1'),
    'cron_offset' => 'CRON_OFFSET_PLACEHOLDER',
];
