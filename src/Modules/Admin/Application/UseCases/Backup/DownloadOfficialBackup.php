<?php

/**
 * Download Official Backup Use Case
 *
 * PHP version 8.1
 *
 * @category Lukaisu
 * @package  Lukaisu\Modules\Admin\Application\UseCases\Backup
 * @author   HugoFara <hugo.farajallah@protonmail.com>
 * @license  Unlicense <http://unlicense.org/>
 * @link     https://hugofara.github.io/lukaisu-server/developer/api
 * @since    3.0.0
 */

declare(strict_types=1);

namespace Lukaisu\Modules\Admin\Application\UseCases\Backup;

use Lukaisu\Modules\Admin\Domain\BackupRepositoryInterface;

/**
 * Use case for generating official Lukaisu Server format backup.
 *
 * This format is compatible with the original Lukaisu Server application.
 *
 * @since 3.0.0
 */
class DownloadOfficialBackup
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
     * Execute the use case - generate and output official backup file.
     *
     * @return never Outputs file and terminates
     */
    public function execute(): never
    {
        $fname = "lukaisu-backup-" . date('Y-m-d-H-i-s') . ".sql.gz";
        $out = "-- " . $fname . "\n";
        $out .= $this->repository->generateOfficialBackupSql();

        header('Content-Type: application/plain');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        echo gzencode($out);
        exit();
    }

    /**
     * Generate official backup content without outputting.
     *
     * @return array{filename: string, content: string} Backup data
     */
    public function generate(): array
    {
        $fname = "lukaisu-backup-" . date('Y-m-d-H-i-s') . ".sql.gz";
        $out = "-- " . $fname . "\n";
        $out .= $this->repository->generateOfficialBackupSql();

        return [
            'filename' => $fname,
            'content' => $out
        ];
    }
}
