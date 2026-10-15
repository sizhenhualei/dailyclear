<?php
/**
 * ============================================================
 *  日清表 · 配置文件（模板）
 *
 *  用法：把本文件复制成同目录下的 config.php，然后按需修改。
 *        cp lib/config.sample.php lib/config.php
 *
 *  只想先跑起来看看？什么都别改，保持 DB_DRIVER = 'sqlite'，
 *  确保 data/ 目录可写，把整个文件夹丢到服务器上就能用。
 * ============================================================
 */

/* ---------- 1. 数据存储 ----------
 * 'sqlite' ：默认。不用建数据库、不用填账号密码，数据存在
 *            data/riqing.sqlite 这一个文件里，整个文件夹传上去就能用。
 * 'mysql'  ：改成 'mysql' 并填好下面 6 行，然后执行 install.sql
 *            （或直接访问首页，程序会尝试自动建表）。
 */
define('DB_DRIVER', 'sqlite');

/* SQLite 数据文件位置（DB_DRIVER = 'sqlite' 时生效）。
 * data/ 目录需要可写；也可以改到站点目录之外，例如 '/www/data/riqing.sqlite'。 */
define('SQLITE_PATH', dirname(__DIR__) . '/data/riqing.sqlite');

/* MySQL 连接信息（DB_DRIVER = 'mysql' 时生效） */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'riqing');
define('DB_USER', 'riqing');
define('DB_PASS', '这里填数据库密码');
define('DB_CHARSET', 'utf8mb4');

/* ---------- 2. 站点信息 ---------- */
define('SITE_NAME', '日清表');
define('SITE_SUB', '');            // 副标题，留空则不显示。例如：'高二(3)班'

/* 时区：决定「今天是哪一天」。服务器在境外时一定要填对，否则会串日期。 */
define('TIMEZONE', 'Asia/Shanghai');

/* 每天几点算新的一天。
 * 0 = 零点整换日（默认）；
 * 4 = 凌晨 4 点之前填写仍然算前一天（晚上熬夜补填不会跳到第二天）。 */
define('DAY_ROLLOVER_HOUR', 0);

/* ---------- 3. 科目 ---------- */
define('SUBJECTS', [
    'chinese'   => '语文',
    'math'      => '数学',
    'english'   => '英语',
    'physics'   => '物理',
    'chemistry' => '化学',
    'biology'   => '生物',
]);

/* 每科每天最多多少个字 */
define('MAX_CONTENT_LEN', 2000);

/* ---------- 4. 后台 ---------- */
/* 留空 = 后台不需要密码（打开 /admin 就能看）。
 * 这个常量目前只是占位，还没有实现登录逻辑。 */
define('ADMIN_PASSWORD', '');
