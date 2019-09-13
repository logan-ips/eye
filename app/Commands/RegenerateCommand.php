<?php

namespace Eyewitness\Eye\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RegenerateCommand extends Command
{
    /**
     * The eye instance.
     *
     * @var \Eyewitness\Eye\Eye;
     */
    protected $eye;

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'eyewitness:regenerate {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Eyewitness.io - regenerate an application token and secret key';

    /**
     * Set the application token and the secret key in the config file.
     *
     * @return mixed
     */
    public function handle()
    {
        if (! $this->option('force') && ! $this->confirmRegenerate()) {
            return $this->info('Aborted. No changes were made. If you need assistance please contact us anytime at: support@eyewitness.io');
        }

        $app_token = $this->generateRandom(16);
        $secret_key = $this->generateRandom(32);

        $this->modifyEnvironmentFile('EYEWITNESS_APP_TOKEN', 'app_token', $app_token);
        $this->modifyEnvironmentFile('EYEWITNESS_SECRET_KEY', 'secret_key', $secret_key);
        $this->modifyEnvironmentFile('EYEWITNESS_SUBSCRIPTION_KEY', 'subscription_key', null);

        $this->laravel['config']['eyewitness.app_token'] = $app_token;
        $this->laravel['config']['eyewitness.secret_key'] = $secret_key;
        $this->laravel['config']['eyewitness.subscription_key'] = null;

        $this->displayOutcome();
        $this->checkCaching();
    }

    /**
     * Update the application environment file with the new config.
     *
     * @param  string  $param
     * @param  string  $type
     * @param  string  $value
     * @return void
     */
    protected function modifyEnvironmentFile($param, $type, $value)
    {
        if ($this->paramAlreadyInFile($param, $type)) {
            $this->overwriteExistingParamToFile($param, $type, $value);
        } else {
            $this->addNewParamToFile($param, $value);
        }
    }

    /**
     * Check if the current .env file already has the param.
     *
     * @param  string  $param
     * @param  string  $type
     * @return bool
     */
    protected function paramAlreadyInFile($param, $type)
    {
        return preg_match($this->replacementPattern($param, $type), $this->getEnvFile());
    }

    /**
     * Check if the current .env file already has the param.
     *
     * @param  string  $param
     * @param  string  $type
     * @param  string  $value
     * @return void
     */
    protected function overwriteExistingParamToFile($param, $type, $value)
    {
        $this->writeEnvFile(preg_replace(
            $this->replacementPattern($param, $type),
            $param.'='.$value,
            $this->getEnvFile()
        ));
    }

    /**
     * Check if the current .env file already has the param.
     *
     * @param  string  $param
     * @param  string  $value
     * @return void
     */
    protected function addNewParamToFile($param, $value)
    {
        $this->writeEnvFile($this->getEnvFile().PHP_EOL.$param.'='.$value);
    }

    /**
     * Get a regex pattern that will match the config type with the param.
     *
     * @param  string  $param
     * @param  string  $type
     * @return string
     */
    protected function replacementPattern($param, $type)
    {
        $escaped = preg_quote('='.$this->laravel['config']['eyewitness.'.$type], '/');

        return "/^{$param}{$escaped}/m";
    }

    /**
     * Display the outcome of the installation.
     *
     * @return void
     */
    protected function displayOutcome()
    {
        $this->info('______________________________________');
        $this->info(' ');
        $this->info('Your new Eyewitness API keys have been updated and saved to your .env file:');
        $this->info(' ');
        $this->table(
          ['Eyewitness', 'Value'],
          [
            ['App Token', config('eyewitness.app_token')],
            ['Secret Key', config('eyewitness.secret_key')]
          ]
        );
        $this->info(' ');
    }

    /**
     * Check if any caching has occured.
     *
     * @return void
     */
    protected function checkCaching()
    {
        if (app()->routesAreCached() && app()->configurationIsCached()) {
            $this->info('p.s. you need to run "config:cache" + "route:cache" + "queue:restart" to ensure your application updates and queue monitoring works correctly.');
        } elseif (app()->configurationIsCached()) {
            $this->info('p.s. you need to run "config:cache" + "queue:restart" to ensure your application updates and queue monitoring works correctly.');
        } elseif (app()->routesAreCached()) {
            $this->info('p.s. you need to run "route:cache" + "queue:restart" to ensure your application updates and queue monitoring works correctly.');
        } else {
            $this->info('p.s. you need to run "queue:restart" to ensure your queue monitoring works correctly.');
        }

        $this->info(' ');
    }

    /**
     * Confirm if the key generation should proceed.
     *
     * @return bool
     */
    protected function confirmRegenerate()
    {
        $this->error('______________________________________');
        $this->error('This will generate a new "app_token" and "secret_key", and overwrite any previous ones in your .env file');
        $this->error('______________________________________');

        return $this->confirm('Do you wish to continue?');
    }

    /**
     * Get the env file contents.
     *
     * @return string
     */
    public function getEnvFile()
    {
        return file_get_contents($this->laravel->environmentPath().'/'.$this->laravel->environmentFile());
    }

    /**
     * Get the env file contents.
     *
     * @param  string  $string
     * @return void
     */
    public function writeEnvFile($string)
    {
        file_put_contents($this->laravel->environmentPath().'/'.$this->laravel->environmentFile(), $string);
    }

    /**
     * Generate some random strings.
     *
     * @param  int  $size
     * @return string
     */
    public function generateRandom($size)
    {
        return Str::random($size);
    }
}
