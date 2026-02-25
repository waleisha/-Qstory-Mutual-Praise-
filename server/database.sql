SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for praise_users
-- ----------------------------
DROP TABLE IF EXISTS `praise_users`;
CREATE TABLE `praise_users` (
  `uin` varchar(20) NOT NULL COMMENT '用户QQ号',
  `update_time` datetime NOT NULL COMMENT '最后活跃时间',
  `ip` varchar(45) NOT NULL COMMENT '客户端IP',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态 0:正常 1:封禁',
  `today_pushed_count` int(11) NOT NULL DEFAULT '0' COMMENT '今日被下发次数',
  `last_push_date` date DEFAULT NULL COMMENT '最后下发日期',
  PRIMARY KEY (`uin`),
  KEY `idx_status_date` (`status`,`last_push_date`),
  KEY `idx_update_time` (`update_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='互赞用户表';

-- ----------------------------
-- Table structure for praise_logs
-- ----------------------------
DROP TABLE IF EXISTS `praise_logs`;
CREATE TABLE `praise_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `provider_uin` varchar(20) NOT NULL COMMENT '点赞提供者',
  `target_uin` varchar(20) NOT NULL COMMENT '点赞目标',
  `push_date` date NOT NULL COMMENT '派发日期',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_provider_target_date` (`provider_uin`,`target_uin`,`push_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='互赞派发日志表';

SET FOREIGN_KEY_CHECKS = 1;
