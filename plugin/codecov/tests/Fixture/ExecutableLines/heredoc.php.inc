<?php
function sql(string $table, array $row): string
{
    $where = <<<SQL
        WHERE id = {$row['id']}
          AND name = '$table'
        SQL;
    $raw = <<<'TXT'
        plain
        TXT;
    return "SELECT * FROM {$table} " . $where . $raw;
}
