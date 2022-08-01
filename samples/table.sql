CREATE TABLE `simpleq` (
  `new` datetime(6) NOT NULL,
  `tagged` datetime(6) DEFAULT NULL,
  `complete` datetime(6) DEFAULT NULL,
  `error` datetime(6) DEFAULT NULL,
  `queue` char(40) CHARACTER SET ascii NOT NULL,
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `token` char(40) CHARACTER SET ascii DEFAULT NULL,
  `checksum` char(40) CHARACTER SET ascii NOT NULL,
  `payload` longblob NOT NULL,
  KEY `idx_token` (`token`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_queue` (`queue`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;