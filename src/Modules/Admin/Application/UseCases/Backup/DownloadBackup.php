<?php

/**
 * Download Backup Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Admin\Application\UseCases\Backup
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Admin\Application\UseCases\Backup;

use Lukaisu\Modules\Admin\Domain\BackupRepositoryInterface;

/**
 * Use case for generating and downloading Lukaisu Server backup.
 */
class DownloadBackup
{
    private BackupRepositoryInterface $repository;

    /**
     * Constructor.
     *
     * @param BackupRepositoryInterface $repository Backup repository
     */
    public function __construct(BackupRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Execute the use case - generate and output backup file.
     *
     * @return never Outputs file and terminates
     */
    public function execute(): never
    {
        $fname = "lukaisu-backup-exp_version-" . date('Y-m-d-H-i-s') . ".sql.gz";
        $out = "-- " . $fname . "\n";
        $out .= $this->repository->generateBackupSql();

        header('Content-Type: application/plain');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        echo gzencode($out);
        exit();
    }

    /**
     * Generate backup content without outputting.
     *
     * Useful for testing or alternative output methods.
     *
     * @return array{filename: string, content: string} Backup data
     */
    public function generate(): array
    {
        $fname = "lukaisu-backup-exp_version-" . date('Y-m-d-H-i-s') . ".sql.gz";
        $out = "-- " . $fname . "\n";
        $out .= $this->repository->generateBackupSql();

        return [
            'filename' => $fname,
            'content' => $out
        ];
    }
}
