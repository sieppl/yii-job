--
-- Tabellenstruktur für Tabelle `job`
--

DROP TABLE IF EXISTS `job`;
CREATE TABLE IF NOT EXISTS `job` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `job_class` varchar(64) NOT NULL,
  `job_data` text,
  `crontab` varchar(128) DEFAULT NULL,
  `planned_time` datetime NOT NULL,
  `start_time` datetime DEFAULT NULL,
  `job_status_id` int(11) NOT NULL,
  `create_time` datetime DEFAULT NULL,
  `update_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `job_log`
--

DROP TABLE IF EXISTS `job_log`;
CREATE TABLE IF NOT EXISTS `job_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `job_class` varchar(64) NOT NULL,
  `start_time` datetime NOT NULL,
  `finish_time` datetime NOT NULL,
  `job_status_id` int(11) NOT NULL,
  `finish_message` text,
  `create_time` datetime DEFAULT NULL,
  `update_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;