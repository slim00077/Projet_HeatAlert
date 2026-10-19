<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use PDOException;

class EnsureDatabase extends Command
{
    protected $signature = 'db:ensure {--database= : The connection to use (defaults to the default connection)}';

    protected $description = 'Create the MySQL database from the .env settings when it does not exist yet';

    public function handle(): int
    {
        $connection = $this->option('database') ?: config('database.default');
        $settings = config("database.connections.{$connection}");

        if (! in_array($settings['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->info('Not a MySQL connection: nothing to do.');

            return self::SUCCESS;
        }

        $database = (string) $settings['database'];

        if (! preg_match('/^[A-Za-z0-9_$-]+$/', $database)) {
            $this->error("Refusing to create a database with the unusual name \"{$database}\".");

            return self::FAILURE;
        }

        try {
            $pdo = $this->connect($settings);
        } catch (PDOException $exception) {
            $this->error('Cannot reach MySQL: '.$exception->getMessage());
            $this->line('Start MySQL, then check DB_HOST, DB_PORT, DB_USERNAME and DB_PASSWORD in .env.');

            return self::FAILURE;
        }

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $this->info("Database \"{$database}\" is ready.");

        return self::SUCCESS;
    }

    /**
     * Connect to the MySQL server itself (no database selected), waiting a little while it starts.
     *
     * @param  array<string, mixed>  $settings
     */
    private function connect(array $settings): PDO
    {
        $dsn = "mysql:host={$settings['host']};port={$settings['port']}";

        for ($attempt = 1; ; $attempt++) {
            try {
                return new PDO($dsn, (string) $settings['username'], (string) $settings['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            } catch (PDOException $exception) {
                if ($attempt >= 5) {
                    throw $exception;
                }

                sleep(2);
            }
        }
    }
}
