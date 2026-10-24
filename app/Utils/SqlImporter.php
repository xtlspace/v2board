<?php

namespace App\Utils;

class SqlImporter
{
    /**
     * 读取 SQL 文件并切分为可逐条执行的语句
     *
     * 注意：不能像旧实现那样用 str_replace("\n", "", $file) 把文件压成一行，
     * 否则多行 ALTER 语句会被拼成 "ALTER TABLE `x`ADD COLUMN ..." 这类语法错误而被静默跳过。
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
        // 去掉整行注释（保留换行，避免注释吞掉后面的语句）
        $content = preg_replace('/^\s*(--|#).*$/m', '', $content);
        $statements = [];
        foreach (explode(';', $content) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            $statements[] = $statement;
        }
        return $statements;
    }
}
