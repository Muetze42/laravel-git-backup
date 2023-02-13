<?php

namespace NormanHuth\LaravelGitBackup\Traits;

use ZipArchive;

trait encryptBackupArchive
{
    protected function encrypt(ZipArchive $zip, string $password): void
    {
        $zip->setPassword($password);

        foreach (range(0, $zip->numFiles - 1) as $i) {
            $zip->setEncryptionIndex($i, static::getAlgorithm());
        }
    }

    protected static function getAlgorithm(): ?int
    {
        $encryption =config('git-backup.database.encryption.algorithm');

        if ($encryption === 'default') {
            $encryption = defined("\ZipArchive::EM_AES_256")
                ? ZipArchive::EM_AES_256
                : null;
        }

        return $encryption;
    }
}
