<?php

namespace App\Utils;

class SqlImporter
{
    /**
     * 读取 SQL 文件并切分为可逐条执行的语句
     *
     * 注意：不能像旧实现那样用 str_replace("\n", "", $file) 把文件压成一行，
     * 否则多行 ALTER 语句会被拼成 "ALTER TABLE `x`ADD COLUMN ..." 这类语法错误而被静默跳过。
     * 同时需要跟踪 DELIMITER，避免把存储过程内部的 ";" 拆开。
     *
     * @param string $file
     * @return array
     */
    public static function statements($file)
    {
        if (!is_file($file)) {
            return [];
        }
        $content = file_get_contents($file);
        if ($content === false || $content === '') {
            return [];
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\n|\r/', $content);
        $delimiter = ';';
        $buffer = '';
        $statements = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (preg_match('/^DELIMITER\s+(\S+)\s*$/i', $trimmed, $matches)) {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                    $buffer = '';
                }
                $delimiter = $matches[1];
                continue;
            }

            $buffer .= $line . "\n";
            $right = rtrim($line);
            if (!str_ends_with($right, $delimiter)) {
                continue;
            }

            $statement = substr(rtrim($buffer), 0, -strlen($delimiter));
            if (trim($statement) !== '') {
                $statements[] = trim($statement);
            }
            $buffer = '';
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }
}
