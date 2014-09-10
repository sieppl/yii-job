--
-- Tabellenstruktur für Tabelle `job`
--

CREATE TABLE `job` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(23) NOT NULL,
  `job_class` varchar(64) NOT NULL,
  `job_data` text,
  `crontab` varchar(128) DEFAULT NULL,
  `planned_time` datetime DEFAULT NULL,
  `start_time` datetime DEFAULT NULL,
  `job_status_id` int(11) NOT NULL,
  `job_origin_id` int(11) NOT NULL,
  `create_time` datetime DEFAULT NULL,
  `update_time` datetime DEFAULT NULL,
  `queue` varchar(45) DEFAULT NULL,
  `progress` int(11) DEFAULT NULL,
  `identifier1` varchar(64) DEFAULT NULL,
  `identifier2` varchar(64) DEFAULT NULL,
  `identifier3` varchar(64) DEFAULT NULL,
  `identifier4` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `job_log`
--

CREATE TABLE `job_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `job_class` varchar(64) NOT NULL,
  `start_time` datetime NOT NULL,
  `finish_time` datetime NOT NULL,
  `job_status_id` int(11) NOT NULL,
  `finish_message` text,
  `create_time` datetime DEFAULT NULL,
  `update_time` datetime DEFAULT NULL,
  `queue` varchar(45) DEFAULT NULL,
  `progress` int(11) DEFAULT NULL,
  `job_data` text,
  `job_id` int(11) DEFAULT NULL,
  `token` varchar(23) DEFAULT NULL,
  `identifier1` varchar(64) DEFAULT NULL,
  `identifier2` varchar(64) DEFAULT NULL,
  `identifier3` varchar(64) DEFAULT NULL,
  `identifier4` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;