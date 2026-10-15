-- ============================================================
--  日清表 · MySQL 建表脚本
--  说明：程序访问首页时会自动建表（CREATE TABLE IF NOT EXISTS），
--        所以这个文件**不是必须**执行的。
--        只有在数据库用户没有建表权限时，才需要在 phpMyAdmin
--        或命令行里手动执行一次。
--  库名：按需替换下面的 `list`
-- ============================================================

-- CREATE DATABASE IF NOT EXISTS `list` DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `list`;

CREATE TABLE IF NOT EXISTS `riqing_records` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `record_date` DATE         NOT NULL COMMENT '哪一天',
    `subject`     VARCHAR(32)  NOT NULL COMMENT '科目 key：chinese/math/english/physics/chemistry/biology',
    `content`     TEXT                  COMMENT '填写的内容',
    `updated_at`  DATETIME     NOT NULL COMMENT '最后更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_date_subject` (`record_date`, `subject`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='日清表记录：一天一科一条';

-- 常用查询走这个唯一键就够了，不需要额外索引。

-- 站点设置（键值对）。目前只用来存主页卡片顺序 subject_order，
-- 例如 'math,chinese,english,physics,chemistry,biology'。
CREATE TABLE IF NOT EXISTS `riqing_settings` (
    `k` VARCHAR(64) NOT NULL COMMENT '设置名',
    `v` TEXT                 COMMENT '设置值',
    PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='站点设置（键值对，例如主页卡片顺序）';
