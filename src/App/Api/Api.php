<?php

namespace Eyewitness\Eye\App\Api;

use Illuminate\Support\Facades\Log as LogFacade;
use Eyewitness\Eye\Eye;
use GuzzleHttp\Client;
use Exception;

class Api
{
    /**
     * The GuzzleHttp client.
     *
     * @var GuzzleHttp\Client
     */
    protected $client;

    /**
     * The Eyewitness.io API url.
     *
     * @var string
     */
    protected $api;

    /**
     * The default headers.
     *
     * @var array
     */
    protected $headers;

    /**
     * Create a new Api instance.
     *
     * @param  \GuzzleHttp\Client  $client
     * @return void
     */
    public function __construct()
    {
        $this->client = app(Client::class);

        $this->eye = app(Eye::class);

        $this->api = config('eyewitness.api_url');

        $this->headers = ['connect_timeout' => 15,
                          'timeout' => 15,
                          'debug' => false];
    }

    /**
     * Install a new Eyewitness application. Will return the newly generated
     * app_token and secret_key from the API.
     *
     * @param  array  $setup
     * @return array
     */
    public function install($setup)
    {
        $this->headers['json'] = $setup;

        $response = $this->client->post($this->api.'/install', $this->headers);

        return json_decode($response->getBody(), true);
    }

    /**
     * Send a ping to request an installation email.
     *
     * @param  string  $email
     * @return void
     */
    public function sendInstallEmail($email)
    {
        $this->ping('install/email', ['email' => $email]);
    }

    /**
     * Send a test ping to the API server.
     *
     * @return mixed
     */
    public function sendTestConnectionPing()
    {
        return $this->ping('test/ping');
    }

    /**
     * Send a ping for the queue.
     *
     * @param  string  $connection
     * @param  string  $tube
     * @return void
     */
    public function sendQueuePing($connection, $tube)
    {
        $this->ping('queue/ping', ['connection' => $connection, 'tube' => $tube]);
    }

    /**
     * Send a ping for the server.
     *
     * @param  array   $data
     * @return void
     */
    public function sendServerPing($data, $scheduler = false)
    {
        $this->ping('server/ping', ['data' => $data, 'scheduler' => $scheduler]);
    }

    /**
     * Send a ping for a failing queue.
     *
     * @param  string  $connection
     * @param  string  $name
     * @param  string  $tube
     * @return void
     */
    public function sendQueueFailingPing($connection, $name, $tube)
    {
        $this->ping('queue/failing', ['connection' => $connection,
                                      'job' => $name,
                                      'tube' => $tube]);
    }

    /**
     * Send a ping for an application exception.
     *
     * @param  string  $level
     * @param  string  $message
     * @return void
     */
    public function sendExceptionAlert($level, $message = null)
    {
        $this->ping('log/exception', ['level' => $level,
                                      'message' => $message]);
    }

    /**
     * Send a start ping for the scheduled event.
     *
     * @param  array  $event
     * @return void
     */
    public function sendSchedulerStartPing($event)
    {
        $this->ping('scheduler/event/start', ['event' => $event]);
    }

    /**
     * Send a finish ping for the scheduled event.
     *
     * @param  array  $event
     * @return void
     */
    public function sendSchedulerFinishPing($event)
    {
        $this->ping('scheduler/event/finish', ['event' => $event]);
    }

    /**
     * Send a webhook ping to the server with a custom message.
     *
     * @param  string  $message
     * @return void
     */
    public function sendWebhookPing($message)
    {
        $this->ping('webhook/ping', ['message' => $message]);
    }

    /**
     * Send the ping that the server is going down for planned maintenance.
     *
     * @return void
     */
    public function down()
    {
        $this->ping('maintenance/down');
    }

    /**
     * Send the ping that the server is now up from planned maintenance.
     *
     * @return void
     */
    public function up()
    {
        $this->ping('maintenance/up');
    }

    /**
     * Run a check for the composer lock against the Symfony Security API.
     *
     * @return json
     */
    public function runComposerLockCheck()
    {
        $this->headers['headers'] = ['Accept' => 'application/json'];

        try {
            if ($this->isRunningGuzzle5()) {
                $this->headers['body'] = ['lock' => fopen(config('eyewitness.composer_lock_file_location'), 'r')];
            } else {
                $this->headers['multipart'] = [['name' => 'lock', 'contents' => fopen(config('eyewitness.composer_lock_file_location'), 'r')]];
            }
            $response = $this->client->post('https://security.symfony.com/check_lock', $this->headers);
            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            LogFacade::error('Symfony Security Composer Lock check failed due to: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Send the ping notification to the Eyewitness.io API.
     *
     * Now includes a 'backoff' retry attempt if the pings fails.
     *
     * @param  string  $route
     * @param  array   $data
     * @return mixed
     */
    protected function ping($route, $data = [])
    {
        if ( ! config('eyewitness.api_enabled')) {
            return [false, 'Api Disabled'];
        }

        $data['app_token'] = config('eyewitness.app_token');
        $data['secret_key'] = config('eyewitness.secret_key');
        $data['application_environment'] = app()->environment();
        $data['eyewitness_version'] = $this->eye->version();

        $this->headers['json'] = $data;

        for ($i=0; $i<3; $i++) {
            sleep($i);
            try {
                $response = $this->client->post($this->api."/$route", $this->headers);
                $result = [$response->getStatusCode(), $response->getReasonPhrase()];
                if ($result[0] == "200") {
                    return $result;
                }
            } catch (Exception $e) {
                $result = [false, $e->getMessage()];
            }
        }

        return $result;
    }

    /**
     * Determine if we are runing Guzzle 5 or 6 to provide dual support in the one package.
     *
     * @return bool
     */
    protected function isRunningGuzzle5()
    {
        $client = get_class($this->client);
        return version_compare($client::VERSION, '6.0.0', "<");
    }
}
