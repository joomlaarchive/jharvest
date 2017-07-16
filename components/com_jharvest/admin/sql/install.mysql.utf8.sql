CREATE TABLE IF NOT EXISTS `#__jharvest_harvests` (
    `id` INTEGER PRIMARY KEY AUTO_INCREMENT,
    `run_once` TINYINT NOT NULL DEFAULT 0 COMMENT 'Allows the harvester to only be run once.',
    `runs` INTEGER NOT NULL DEFAULT 0 COMMENT 'The number of times the harvester has run.',
    `harvested` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
    `params` TEXT NOT NULL,
    `state` TINYINT NOT NULL DEFAULT 0 COMMENT 'The published state of the harvest.',
    `created` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
    `created_by` INTEGER NOT NULL DEFAULT 0,
    `modified` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
    `modified_by` INTEGER NOT NULL DEFAULT 0,
    `checked_out` INTEGER NOT NULL DEFAULT 0,
    `checked_out_time` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
    KEY `idx_jharvest_harvests_checkout` (`checked_out`),
    KEY `idx_jharvest_harvests_published` (`state`),
    KEY `idx_jharvest_harvests_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- A holding table for records harvested.

CREATE TABLE IF NOT EXISTS `#__jharvest_cache` (
    `id` VARCHAR(255),
    `data` TEXT NULL,
    `state` INT 0,
    `harvest_id` INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY(`id`, `harvest_id`),
    KEY `idx_jharvest_cache_harvest_id` (`harvest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
