# Laravel Git Backup

Actually, I didn't want to make it as a (public) package. So I kept everything short and simple in this readme.

This package create a backup via Git.

A few code parts are taken from [spatie/laravel-backup](https://github.com/spatie/laravel-backup). Also, the [spatie/db-dumper](https://github.com/spatie/db-dumper) from spatie is used.

## Configuration

In the default configuration, Laravel `storage/app` folder uses for backup include a dump of `mysql` database connection. In this case, the Git repository is located here: `storage/app/.git`.

To change the configuration publish the [config/git-backup.php](config/git-backup.php) file via command line:

```shell
php artisan vendor:publish --provider="NormanHuth\LaravelGitBackup\ServiceProvider"
```

## Usage

### Run the backup manually

```shell
php artisan git:backup:run
```

### Scheduling

For example use [Laravel Task Scheduling](https://laravel.com/docs/scheduling)

```shell
$schedule->command('git:backup:run')->dailyAt('4:00');
```
