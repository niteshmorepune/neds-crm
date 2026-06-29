<?php

namespace App\Support;

use PDO;

class PdoDumper
{
    private const INSERT_BATCH = 500;

    /**
     * Dump all tables in the given MySQL database to a SQL string using
     * only PDO — no shell calls. Used when proc_open / mysqldump is
     * unavailable on the host.
     *
     * @param  array<string, mixed>  $db
     */
    public function dump(array $db): string
    {
        $pdo = $this->connect($db);

        $lines = [
            '-- NEDS CRM pure-PHP PDO dump (proc_open fallback)',
            '-- Generated: '.now()->toDateTimeString(),
            '',
            'SET NAMES utf8mb4;',
            'SET FOREIGN_KEY_CHECKS=0;',
            '',
        ];

        /** @var list<string> $tables */
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            array_push($lines, ...$this->tableStructure($pdo, $table));
            array_push($lines, ...$this->tableData($pdo, $table));
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $db */
    private function connect(array $db): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $db['host'] ?? '127.0.0.1',
            $db['port'] ?? '3306',
            $db['database'],
        );

        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

        // Unbuffered queries avoid loading large tables entirely into PHP memory.
        // Constant only exists when pdo_mysql is loaded, which is guaranteed for
        // MySQL connections but may be absent in test environments.
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = false;
        }

        return new PDO($dsn, $db['username'] ?? 'root', (string) ($db['password'] ?? ''), $options);
    }

    /** @return list<string> */
    private function tableStructure(PDO $pdo, string $table): array
    {
        /** @var array{0: string, 1: string} $row */
        $row = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);

        return [
            "-- Table: `{$table}`",
            "DROP TABLE IF EXISTS `{$table}`;",
            $row[1].';',
            '',
        ];
    }

    /** @return list<string> */
    private function tableData(PDO $pdo, string $table): array
    {
        $stmt = $pdo->query("SELECT * FROM `{$table}`");
        $lines = [];
        $cols = null;
        $batch = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($cols === null) {
                $cols = '`'.implode('`, `', array_keys($row)).'`';
            }

            $values = array_map(
                static fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                $row,
            );
            $batch[] = '('.implode(', ', $values).')';

            if (count($batch) >= self::INSERT_BATCH) {
                $lines[] = "INSERT INTO `{$table}` ({$cols}) VALUES";
                $lines[] = implode(",\n", $batch).';';
                $batch = [];
            }
        }

        if ($batch !== []) {
            $lines[] = "INSERT INTO `{$table}` ({$cols}) VALUES";
            $lines[] = implode(",\n", $batch).';';
        }

        $lines[] = '';

        return $lines;
    }
}
