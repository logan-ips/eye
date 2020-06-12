<?php

namespace Eyewitness\Eye\Monitors;

use Exception;
use Illuminate\Support\Facades\DB;
use Eyewitness\Eye\Repo\History\Database as History;
use Eyewitness\Eye\Notifications\Messages\Database\SizeOk;
use Eyewitness\Eye\Notifications\Messages\Database\Online;
use Eyewitness\Eye\Notifications\Messages\Database\Offline;
use Eyewitness\Eye\Notifications\Messages\Database\SizeSmall;
use Eyewitness\Eye\Notifications\Messages\Database\SizeLarge;

class Database extends BaseMonitor
{
    /**
     * Perform poll for each database.
     *
     * @return array
     */
    public function poll($statusOnly = false)
    {
        foreach($this->getDatabases() as $db)
        {
            $this->handleStatusAlerts($db);

            if ($db['status'] && !$statusOnly) {
                $this->handleSizeAlerts($db);

                History::create(['type' => 'database',
                                 'meta' => $db['connection'],
                                 'record' => [],
                                 'value' => $db['size']]);
            }
        }
    }

    /**
     * Handle any status issues.
     *
     * @param  string  $db
     * @return void
     */
    protected function handleStatusAlerts($db)
    {
        if ($db['status']) {
            if ($this->eye->status()->isSick('database_status_'.$db['connection'])) {
                $this->eye->notifier()->alert(new Online($db));
            }

            $this->eye->status()->setHealthy('database_status_'.$db['connection']);
        } else {
            if ($this->eye->status()->isHealthy('database_status_'.$db['connection'])) {
                $this->eye->notifier()->alert(new Offline($db));
            }

            $this->eye->status()->setSick('database_status_'.$db['connection']);
        }

    }

    /**
     * Handle any size issues.
     *
     * @param  string  $db
     * @return void
     */
    protected function handleSizeAlerts($db)
    {
        if (($db['alert_greater_than_mb'] > 0) && ($db['size'] > $db['alert_greater_than_mb'])) {
            if ($this->eye->status()->isHealthy('database_size_'.$db['connection'])) {
                $this->eye->notifier()->alert(new SizeLarge($db));
            }

            $this->eye->status()->setSick('database_size_'.$db['connection']);
        } elseif (($db['alert_less_than_mb'] > 0) && ($db['size'] < $db['alert_less_than_mb'])) {
            if ($this->eye->status()->isHealthy('database_size_'.$db['connection'])) {
                $this->eye->notifier()->alert(new SizeSmall($db));
            }

            $this->eye->status()->setSick('database_size_'.$db['connection']);
        } else {
            if ($this->eye->status()->isSick('database_size_'.$db['connection'])) {
                $this->eye->notifier()->alert(new SizeOk($db));
            }

            $this->eye->status()->setHealthy('database_size_'.$db['connection']);
        }

    }

    /**
     * Perform poll for each database.
     *
     * @return array
     */
    public function getDatabases()
    {
        $data = [];

        foreach ($this->getMonitoredDatabases() as $db) {
            $data[] = ['connection' => $db['connection'],
                       'alert_greater_than_mb' => $db['alert_greater_than_mb'],
                       'alert_less_than_mb' => $db['alert_less_than_mb'],
                       'driver' => $this->getDriverName($db['connection']),
                       'status' => $this->checkDatabaseStatus($db['connection']),
                       'size' => $this->getDatabaseSize($db['connection'])];
        }

        return $data;
    }

    /**
     * Check if the database connection is working.
     *
     * @param  string  $connection
     * @return bool
     */
    protected function checkDatabaseStatus($connection)
    {
        try {
            DB::connection($connection)->getPdo();
        } catch (Exception $e) {
            $this->eye->logger()->error('DB status failed', $e, $connection);
            return false;
        }

        return true;
    }

    /**
     * Get the size of the database.
     *
     * @param  string  $connection
     * @return int
     */
    protected function getDatabaseSize($connection)
    {
        switch ($this->getDriverName($connection)) {
            case 'mysql':
                $size = $this->checkMySqlDatabaseSize($connection);
                break;
            case 'sqlite':
                $size = $this->checkSqLiteDatabaseSize($connection);
                break;
            case 'pgsql':
                $size = $this->checkPostgresDatabaseSize($connection);
                break;
            case 'sqlsrv':
                $size = $this->checkSqlServerDatabaseSize($connection);
                break;
            default:
                $this->eye->logger()->debug('Unable to find DB driver for size', $connection);
                return -1;
        }

        return round($size/1024/1024, 2);
    }

    /**
     * Return the size of the mySql database.
     *
     * @param  string  $connection
     * @return int
     */
    protected function checkMySqlDatabaseSize($connection)
    {
        try {
            $result = (array) DB::connection($connection)
                                ->table('information_schema.TABLES')
                                ->select(DB::raw("sum( data_length + index_length ) AS size"))
                                ->where('table_schema', DB::connection($connection)->getConfig('database'))
                                ->first();

            return $result['size'];
        } catch (Exception $e) {
            $this->eye->logger()->debug('Error during mySQL DB size check', ['exception' => $e->getMessage(), 'connection' => $connection]);
        }

        return -1;
    }

    /**
     * Return the size of the Sqlite database.
     *
     * @param  string  $connection
     * @return int
     */
    protected function checkSqLiteDatabaseSize($connection)
    {
        try {
            $path = realpath(DB::connection($connection)->getConfig('database'));
            if ($path !== false) {
                return filesize($path);
            }
        } catch (Exception $e) {
            $this->eye->logger()->debug('Error during sqLite DB size check', ['exception' => $e->getMessage(), 'connection' => $connection]);
        }

        return -1;
    }

    protected function checkSqlServerDatabaseSize($connection)
    {
        try {
            $result = DB::connection($connection)
                ->select(
                    DB::raw(
                        "SELECT total_size_mb = CAST(SUM(size) * 8 AS DECIMAL(16,2)) FROM sys.master_files WITH(NOWAIT) WHERE DB_NAME(database_id) = '{$connection}' GROUP BY database_id"
                    )
                );
            $result = (array)$result[0];

            return $result['total_size_mb'] * 1024;
        } catch (Exception $e) {
            $this->eye->logger()
                ->debug(
                    'Error during SQL Server DB size check',
                    ['exception' => $e->getMessage(), 'connection' => $connection]
                );
        }

        return -1;
    }

    /**
     * Return the size of the Postgres database.
     *
     * @param  string  $connection
     * @return int
     */
    protected function checkPostgresDatabaseSize($connection)
    {
        try {
            $result = DB::connection($connection)->select(DB::raw("select pg_database_size('".DB::connection($connection)->getConfig('database')."')"));
            $result = (array) $result[0];
            return $result['pg_database_size'];
        } catch (Exception $e) {
            $this->eye->logger()->debug('Error during Postgres DB size check', ['exception' => $e->getMessage(), 'connection' => $connection]);
        }

        return -1;
    }

    /**
     * Get the name of the database driver.
     *
     * @param  string  $connection
     * @return string
     */
    protected function getDriverName($connection)
    {
        try {
            return DB::connection($connection)->getDriverName();
        } catch (Exception $e) {
            $this->eye->logger()->debug('Unable to find DB driver', $connection);
        }

        return 'unknown';
    }

    /**
     * Get the list of databases we are monitoring.
     *
     * @return array
     */
    protected function getMonitoredDatabases()
    {
        if (is_array(config('eyewitness.database_connections')) &&
            count(config('eyewitness.database_connections')) > 0) {
            return config('eyewitness.database_connections');
        }

        return [
            [
                'connection' => config('database.default'),
                'alert_greater_than_mb' => 0,
                'alert_less_than_mb' => 0
            ]
        ];
    }
}
