<?php
/**
 * 数据库连接（PDO）+ 自动建表。
 * 支持两种驱动，见 lib/config.php：sqlite（默认，零配置）/ mysql。
 * 所有 SQL 都用预处理占位符，避免注入。
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * 取全局 PDO 连接（单例，一次请求只连一次）。
 *
 * @return PDO
 */
function rq_db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $driver  = strtolower(DB_DRIVER);
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    try {
        if ($driver === 'mysql') {
            // 用真正的预处理语句（mysql 驱动支持）
            $options[PDO::ATTR_EMULATE_PREPARES] = false;
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } else {
            $dir = dirname(SQLITE_PATH);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (!is_dir($dir) || !is_writable($dir)) {
                rq_fatal(
                    '数据目录不可写',
                    '目录：' . $dir,
                    '把 data/ 目录权限设为 755（Linux）后重试；或者修改 lib/config.php 里的 SQLITE_PATH 指向一个可写目录。'
                );
            }
            $pdo = new PDO('sqlite:' . SQLITE_PATH, null, null, $options);
            try {
                $pdo->exec('PRAGMA journal_mode = WAL');
                $pdo->exec('PRAGMA busy_timeout = 5000');
            } catch (PDOException $ignore) {
                // WAL 只是性能优化，失败不影响使用
            }
        }
    } catch (PDOException $e) {
        $hint = ($driver === 'mysql')
            ? '请检查 lib/config.php 里的 DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS，并确认数据库已经建好（可先执行 install.sql）。'
            : '请确认 data/ 目录存在且 PHP 进程有写权限。';
        rq_fatal('数据库连接失败', $e->getMessage(), $hint);
    }

    rq_db_migrate($pdo, $driver);
    return $pdo;
}

/**
 * 建表（已存在则跳过）。所以第一次访问就能直接用，不用手动导入 SQL。
 */
function rq_db_migrate(PDO $pdo, $driver)
{
    try {
        if ($driver === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `riqing_records` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `record_date` DATE NOT NULL,
                `subject` VARCHAR(32) NOT NULL,
                `content` TEXT,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_date_subject` (`record_date`, `subject`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `riqing_settings` (
                `k` VARCHAR(64) NOT NULL COMMENT '设置名',
                `v` TEXT COMMENT '设置值',
                PRIMARY KEY (`k`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='站点设置（键值对，例如主页卡片顺序）'");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS riqing_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                record_date TEXT NOT NULL,
                subject TEXT NOT NULL,
                content TEXT,
                updated_at TEXT NOT NULL
            )");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uniq_date_subject ON riqing_records (record_date, subject)");

            $pdo->exec("CREATE TABLE IF NOT EXISTS riqing_settings (
                k TEXT PRIMARY KEY,
                v TEXT
            )");
        }
    } catch (PDOException $e) {
        rq_fatal(
            '建表失败',
            $e->getMessage(),
            '如果数据库用户没有建表权限，请先用 install.sql 手动建表，再刷新本页。'
        );
    }
}
