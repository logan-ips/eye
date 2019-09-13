<?php

use Faker\Generator as Faker;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Used by Eyewitness for testing the package.
|
*/

$factory->define(\Eyewitness\Eye\Repo\Statuses::class, function (Faker $faker) {
    return [
        'monitor' => 'debug',
        'healthy' => '1',
    ];
});


$factory->define(\Eyewitness\Eye\Repo\Scheduler::class, function (Faker $faker) {
    return [
        'schedule' => '* * * * *',
        'command' => $faker->word.':'.$faker->word,
        'timezone' => 'UTC',
        'without_overlapping' => false,
        'run_in_background' => false,
        'on_one_server' => false,
        'mutex' => uniqid(Str::random(30), true),
        'healthy' => 1,
        'last_run' => date('Y-m-d H:i:s'),
        'next_run_due' => date('Y-m-d H:i:s'),
        'next_check_due' => date('Y-m-d H:i:s'),
        'alert_on_missed' => 1,
        'alert_on_fail' => 1,
        'alert_run_time_greater_than' => 0,
        'alert_run_time_less_than' => 0,
        'alert_last_run_greater_than' => 0,
    ];
});

$factory->define(\Eyewitness\Eye\Repo\History\Scheduler::class, function (Faker $faker) {
    return [
        'scheduler_id' => 1,
        'time_to_run' => (rand(1,100)/10),
        'exitcode' => 0,
        'overdue' => 0,
        'output' => $faker->sentence,
        'expected_completion' => date('Y-m-d H:i:s'),
    ];
});

$factory->define(\Eyewitness\Eye\Repo\Notifications\Recipient::class, function (Faker $faker) {
    return [
        'type' => 'email',
        'address' => 'test@test.com',
        'low' => 1,
        'medium' => 1,
        'high' => 1,
    ];
});

$factory->define(\Eyewitness\Eye\Repo\Notifications\Severity::class, function (Faker $faker) {
    return [
        'namespace' => 'test_notification',
        'notification' => 'Eyewitness\Eye\Notifications\Messages\TestMessage',
        'severity' => 'high',
    ];
});

$factory->define(\Eyewitness\Eye\Repo\Notifications\History::class, function (Faker $faker) {
    return [
        'type' => 'Test Notification',
        'meta' => [],
        'acknowledged' => 0,
        'isError' => 0,
        'title' => 'Test Notification',
        'description' => 'This is a test',
        'severity' => 'low'
    ];
});

$factory->define(\Eyewitness\Eye\Repo\History\Dns::class, function (Faker $faker) {
    return [
        'type' => 'dns',
        'meta' => 'http://example.com',
        'record' => [['example' => 'record']],
        'value' => null
    ];
});

$factory->define(\Eyewitness\Eye\Repo\History\Ssl::class, function (Faker $faker) {
    return [
        'type' => 'ssl',
        'meta' => 'http://example.com',
        'record' => [['example' => 'record']],
        'value' => null
    ];
});

$factory->define(\Eyewitness\Eye\Repo\History\Database::class, function (Faker $faker) {
    return [
        'type' => 'database',
        'meta' => 'mysql',
        'record' => [],
        'value' => 363,
    ];
});

$factory->define(\Eyewitness\Eye\Repo\History\Custom::class, function (Faker $faker) {
    return [
        'type' => 'custom',
        'meta' => 'MyClass',
        'record' => [],
        'value' => 363,
    ];
});


$factory->define(\Eyewitness\Eye\Repo\History\Composer::class, function (Faker $faker) {
    return [
        'type' => 'composer',
        'meta' => 'composer',
        'record' => ['test/package' => [
                        'version' => '2.0.0',
                        'advisories' => [
                            'test/pacakge/2017-05-09.yaml' => [
                                'title' => 'Example of Composer Problem',
                                'link' => 'https://example.com'
        ]]]],
        'value' => null,
    ];
});
