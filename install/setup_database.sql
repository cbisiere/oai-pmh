
CREATE DATABASE IF NOT EXISTS `oai_repo`;

CREATE USER IF NOT EXISTS 'oai_user'@'localhost' IDENTIFIED BY 'demo';
GRANT SELECT, INSERT, DELETE, UPDATE ON `oai_repo`.* TO 'oai_user'@'localhost';


USE oai_repo

CREATE TABLE IF NOT EXISTS `oai_repo` (
  `id` varchar(12) NOT NULL,
  `repositoryName` tinytext NOT NULL,
  `baseURL` tinytext NOT NULL,
  `protocolVersion` varchar(5) NOT NULL,
  `adminEmails` tinytext NOT NULL COMMENT 'csv',
  `earliestDatestamp` varchar(20) NOT NULL,
  `deletedRecord` enum('no','transient','persistent') NOT NULL,
  `granularity` enum('YYYY-MM-DD','YYYY-MM-DDThh:mm:ssZ') NOT NULL,
  `maxListSize` int UNSIGNED DEFAULT NULL,
  `tokenDuration` int UNSIGNED DEFAULT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `comment` tinytext,

  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=Utf8mb4;


CREATE TABLE IF NOT EXISTS `oai_repo_description` (
  `repo` varchar(12) NOT NULL DEFAULT '1',
  `description` text COMMENT 'xml',
  `rank` int NOT NULL DEFAULT '0',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `comment` tinytext,

  PRIMARY KEY (`repo`,`rank`),

  FOREIGN KEY `fk_oai_repo_description_oai_repo` (`repo`)
    REFERENCES `oai_repo` (`id`)
    ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=Utf8mb4;


CREATE TABLE IF NOT EXISTS `oai_meta` (
  `repo` varchar(12) NOT NULL DEFAULT '1',
  `metadataPrefix` varchar(20) NOT NULL,
  `schema` tinytext NOT NULL,
  `metadataNamespace` tinytext NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `comment` tinytext,

  PRIMARY KEY (`repo`,`metadataPrefix`),

  FOREIGN KEY `fk_oai_meta_oai_repo` (`repo`)
    REFERENCES `oai_repo` (`id`)
    ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=Utf8mb4;


CREATE TABLE IF NOT EXISTS `oai_set` (
  `repo` varchar(12) NOT NULL DEFAULT '1',
  `setSpec` varchar(60) NOT NULL,
  `setName` tinytext NOT NULL,
  `rank` int NOT NULL DEFAULT '0',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `comment` tinytext,

  PRIMARY KEY (`repo`,`setSpec`),

  FOREIGN KEY `fk_oai_repo_set_oai_repo` (`repo`)
    REFERENCES `oai_repo` (`id`)
    ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=Utf8mb4;


CREATE TABLE IF NOT EXISTS `oai_set_description` (
  `repo` varchar(12) NOT NULL DEFAULT '1',
  `setSpec` varchar(60) NOT NULL,
  `setDescription` text COMMENT 'xml',
  `rank` int NOT NULL DEFAULT '0',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `comment` tinytext,

  PRIMARY KEY (`repo`,`setSpec`),

  FOREIGN KEY `fk_oai_repo_set_description_oai_repo` (`repo`)
    REFERENCES `oai_repo` (`id`)
    ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=Utf8mb4;


CREATE TABLE IF NOT EXISTS `oai_item_meta` (
  `repo` varchar(12) NOT NULL DEFAULT '1',
  `history` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `serial` int UNSIGNED NOT NULL DEFAULT '0',
  `identifier` varchar(200) NOT NULL,
  `metadataPrefix` varchar(20) NOT NULL,
  `datestamp` datetime NOT NULL,
  `deleted` tinyint NOT NULL,
  `metadata` text COMMENT 'xml',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX `idx_repo_item_meta` (`repo`,`identifier`,`metadataPrefix`),

  PRIMARY KEY (`repo`,`history`,`serial`,`identifier`,`metadataPrefix`),

  FOREIGN KEY `fk_oai_item_meta_oai_repo` (`repo`)
    REFERENCES `oai_repo` (`id`)
    ON DELETE CASCADE,
  FOREIGN KEY `fk_oai_item_meta_oai_meta` (`repo`, `metadataPrefix`)
    REFERENCES `oai_meta` (`repo`, `metadataPrefix`)
    ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=Utf8mb4;


CREATE TABLE IF NOT EXISTS `oai_item_meta_about` (
  `repo` varchar(12) NOT NULL DEFAULT '1',
  `history` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `serial` int UNSIGNED NOT NULL DEFAULT '0',
  `identifier` varchar(200) NOT NULL,
  `metadataPrefix` varchar(20) NOT NULL,
  `datestamp` datetime NOT NULL,
  `about` text COMMENT 'xml',
  `rank` int NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX `idx_repo_item_meta_about` (`repo`,`identifier`,`metadataPrefix`,`rank`),

  PRIMARY KEY (`repo`,`history`,`serial`,`identifier`,`metadataPrefix`,`rank`),

  FOREIGN KEY `fk_oai_item_meta_about_oai_repo` (`repo`)
    REFERENCES `oai_repo` (`id`)
    ON DELETE CASCADE,
  FOREIGN KEY `fk_oai_item_meta_about_oai_meta` (`repo`, `metadataPrefix`)
    REFERENCES `oai_meta` (`repo`, `metadataPrefix`)
    ON DELETE CASCADE,
  FOREIGN KEY `fk_oai_item_meta_about_oai_item_meta` (`repo`, `identifier`, `metadataPrefix`)
    REFERENCES `oai_item_meta` (`repo`, `identifier`, `metadataPrefix`)
    ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=Utf8mb4;

/* soft-delete corresponding 'about' records when a metadata record is 
 archived  */
DELIMITER $$
CREATE TRIGGER trigger_oai_about_soft_delete
AFTER UPDATE ON `oai_item_meta` FOR EACH ROW
BEGIN
  IF (OLD.`history` = 0) AND (NEW.`history` = 1) THEN
    UPDATE `oai_item_meta_about` 
      SET `history` = 1 
      WHERE `repo` = NEW.`repo` 
        AND `history` = 0
        AND `identifier` = NEW.`identifier`
        AND `metadataPrefix` = NEW.`metadataPrefix`;
  END IF;
END;
$$
DELIMITER ;

CREATE TABLE IF NOT EXISTS `oai_item_set` (
  `repo` varchar(12) NOT NULL DEFAULT '1',
  `history` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `serial` int UNSIGNED NOT NULL DEFAULT '0',
  `identifier` varchar(200) NOT NULL,
  `metadataPrefix` varchar(20) NOT NULL,
  `setSpec` varchar(60) NOT NULL,
  `confirmed` int UNSIGNED NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`repo`,`history`,`serial`,`identifier`,`metadataPrefix`,`setSpec`),

  FOREIGN KEY `fk_oai_item_set_oai_repo` (`repo`)
    REFERENCES `oai_repo` (`id`)
    ON DELETE CASCADE,
  FOREIGN KEY `fk_oai_item_set_oai_meta` (`repo`, `metadataPrefix`)
    REFERENCES `oai_meta` (`repo`, `metadataPrefix`)
    ON DELETE CASCADE,
  FOREIGN KEY `fk_oai_item_set_oai_set` (`repo`, `setSpec`)
    REFERENCES `oai_set` (`repo`, `setSpec`)
    ON DELETE CASCADE,
  FOREIGN KEY `fk_oai_item_set_oai_item_meta` (`repo`, `identifier`, `metadataPrefix`)
    REFERENCES `oai_item_meta` (`repo`, `identifier`, `metadataPrefix`)
    ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=Utf8mb4;


CREATE TABLE IF NOT EXISTS `oai_access_log` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `repo` varchar(12) NOT NULL,
  `address` varchar(40) DEFAULT NULL,
  `host` text,
  `referer` text,
  `agent` text,
  `uri` text,
  `method` varchar(10) DEFAULT NULL,
  `date_start` datetime DEFAULT NULL,
  `date_end` datetime DEFAULT NULL,
  `duration` double DEFAULT NULL,
  `error` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `errno` int DEFAULT NULL,
  `errmsg` text,
  `request_verb` varchar(20) DEFAULT NULL,
  `request_prefix` varchar(20) DEFAULT NULL,
  `request_set` varchar(60) DEFAULT NULL,
  `request_identifier` varchar(200) DEFAULT NULL,
  `request_from` datetime DEFAULT NULL,
  `request_until` datetime DEFAULT NULL,
  `request_token` text,
  `response_date` datetime DEFAULT NULL,
  `response_error_code` varchar(25) DEFAULT NULL,
  `response_error_message` text,
  `response_token` text,
  `response_cursor` int DEFAULT NULL,
  `response` text,
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  FOREIGN KEY `fk_oai_access_log_oai_repo` (`repo`)
    REFERENCES `oai_repo` (`id`)
    ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=Utf8mb4;


CREATE TABLE IF NOT EXISTS `oai_update_log` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `repo` varchar(12) NOT NULL,
  `task` text,
  `date_start` datetime DEFAULT NULL,
  `date_end` datetime DEFAULT NULL,
  `status` enum('committed','rolled back','commit failed','rollback failed') DEFAULT NULL,
  `error` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `errno` int DEFAULT NULL,
  `errmsg` text,
  `warning` text,
  `meta_inserted` int UNSIGNED NOT NULL DEFAULT '0',
  `meta_deleted` int UNSIGNED NOT NULL DEFAULT '0',
  `meta_touched` int UNSIGNED NOT NULL DEFAULT '0',
  `set_inserted` int UNSIGNED NOT NULL DEFAULT '0',
  `set_deleted` int UNSIGNED NOT NULL DEFAULT '0',
  `updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  FOREIGN KEY `fk_oai_update_log_oai_repo` (`repo`)
    REFERENCES `oai_repo` (`id`)
    ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=Utf8mb4;
