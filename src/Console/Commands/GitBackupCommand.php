<?php

namespace NormanHuth\LaravelGitBackup\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use NormanHuth\LaravelGitBackup\Traits\encryptBackupArchive;
use NormanHuth\LaravelGitBackup\Traits\processExtraDumpParameters;
use Spatie\DbDumper\Databases\MongoDb;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Databases\PostgreSql;
use Spatie\DbDumper\Databases\Sqlite;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use ZipArchive;

class GitBackupCommand extends Command
{
    use encryptBackupArchive;
    use processExtraDumpParameters;

    /**
     * Git repository directory
     *
     * @var string
     */
    protected string $directory;

    /**
     * Database directory in the Git repository
     *
     * @var string
     */
    protected string $dbDir;

    /**
     * The Filesystem disk instance
     *
     * @var Filesystem
     */
    protected Filesystem $disk;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'git:backup:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * The Carbon instance for the current time
     *
     * @var Carbon
     */
    protected Carbon $now;

    /**
     * Execute the console command.
     *
     * @throws Exception
     */
    public function handle()
    {
        $this->now = now();

        $this->directory = config('git-backup.directory', storage_path('app'));
        $this->dbDir = trim(config('git-backup.database.directory', 'database'), '/\\');

        $this->disk = Storage::build([
            'driver' => config('git-backup.storage-driver', 'local'),
            'root'   => rtrim(config('git-backup.directory', storage_path('app')), '/\\'),
        ]);

        $this->handleDatabaseDumps();
        $this->pushGit();
    }

    /**
     * Commit and push changes
     *
     * @return void
     */
    protected function pushGit(): void
    {
        @set_time_limit(0);

        $gitCommand = config('git-backup.command', 'git add -A -f && git commit -m "{toDateTimeString}" && git push');

        $gitCommand = preg_replace_callback(
            '/{(.*?)}/',
            function ($match) {
                if (isset($match[1]) && method_exists($this->now, $match[1])) {
                    return $this->now->{$match[1]}();
                }
                return $match[0];
            },
            $gitCommand
        );

        $commands = [
            'cd '.$this->disk->path('/'),
            $gitCommand
        ];

        $process = Process::fromShellCommandline(implode(' && ', $commands));
        $process->setTimeout(null);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * Dump selected database connections
     *
     * @throws Exception
     * @return void
     */
    protected function handleDatabaseDumps(): void
    {
        $databases = config('git-backup.database.connections', []);
        foreach ($databases as $databaseConfig) {
            $this->dumpDatabase($databaseConfig);
        }
    }

    /**
     * Dump database
     *
     * @throws Exception
     */
    protected function dumpDatabase(string $connection): void
    {
        $databaseConfig = config('database.connections.'.$connection);

        $dumper = static::dumper($databaseConfig['driver'])
            ->setHost(Arr::first(Arr::wrap($databaseConfig['host'] ?? '')))
            ->setDbName($databaseConfig['connect_via_database'] ?? $databaseConfig['database'])
            ->setUserName($databaseConfig['username'] ?? '')
            ->setPassword($databaseConfig['password'] ?? '');

        $excludeTables = config('git-backup.database.exclude-tables.'.$connection, []);
        if (!empty($excludeTables)) {
            $dumper->excludeTables($excludeTables);
        }

        if ($dumper instanceof MySql) {
            $dumper->setDefaultCharacterSet($dbConfig['charset'] ?? '');
        }

        if ($dumper instanceof MongoDb) {
            $dumper->setAuthenticationDatabase($dbConfig['dump']['mongodb_user_auth'] ?? '');
        }

        if (isset($dbConfig['port'])) {
            $dumper = $dumper->setPort($dbConfig['port']);
        }

        if (isset($dbConfig['dump'])) {
            $dumper = static::processExtraDumpParameters($dbConfig['dump'], $dumper);
        }

        if (isset($dbConfig['unix_socket'])) {
            $dumper = $dumper->setSocket($dbConfig['unix_socket']);
        }

        $targetDir = $this->dbDir.DIRECTORY_SEPARATOR;

        $filename = trim(config(
            'git-backup.database.filenames.'.$connection,
            '{driver}/{database}-{toDateTimeString}'
        ), '/\\');
        $replace = [
            '{database}' => $databaseConfig['connect_via_database'] ?? $databaseConfig['database'],
            '{username}' => $databaseConfig['username'],
            '{driver}'   => $databaseConfig['driver'],
            '{host}'     => $databaseConfig['host'],
        ];
        $filename = str_replace(array_keys($replace), array_values($replace), $filename);
        $filename = preg_replace_callback(
            '/{date-(.*?)}/',
            function ($match) {
                if (isset($match[1])) {
                    return $this->now->format($match[1]);
                }
                return $match[0];
            },
            $filename
        );
        $filename = preg_replace_callback(
            '/{(.*?)}/',
            function ($match) {
                if (isset($match[1]) && method_exists($this->now, $match[1])) {
                    return $this->now->{$match[1]}();
                }
                return $match[0];
            },
            $filename
        );
        $filename = str_replace('\\', '/', $filename);
        $filename = explode('/', $filename);
        $filename = array_map(function ($value) {
            return Str::slug($value);
        }, $filename);
        $filePath = $filename;
        unset($filePath[array_key_last($filePath)]);
        $filename = implode(DIRECTORY_SEPARATOR, $filename);
        $filePath = implode(DIRECTORY_SEPARATOR, $filePath);

        if (!$this->disk->directoryExists($targetDir.DIRECTORY_SEPARATOR.$filePath)) {
            $this->disk->makeDirectory($targetDir.DIRECTORY_SEPARATOR.$filePath);
        }

        $target = $targetDir.DIRECTORY_SEPARATOR.$filename;

        $sqlFilePath = $target.'.sql';
        $sqlFile = $this->disk->path($sqlFilePath);
        $zipFile = $this->disk->path($target.'.zip');

        $dumper->dumpToFile($sqlFile);

        $zip = new ZipArchive;
        $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zipPassword = config('git-backup.database.passwords.'.$connection);
        if ($zipPassword) {
            $this->encrypt($zip, $zipPassword);
        }
        $zip->addFile($sqlFile, basename($sqlFile));
        if ($zip->close()) {
            $this->disk->delete($sqlFilePath);
        }
    }

    /**
     * Get dumper class
     *
     * @throws Exception
     */
    protected static function dumper(string $driver): MySql|Sqlite|PostgreSql|MongoDb
    {
        return match ($driver) {
            'mysql', 'mariadb' => new MySql,
            'pgsql' => new PostgreSql,
            'sqlite' => new Sqlite,
            'mongodb' => new MongoDb,
            default => throw new Exception('Cannot create a dumper for db driver `'.$driver.'`. Use `mysql`, `pgsql`, `mongodb` or `sqlite`.'),
        };
    }
}
