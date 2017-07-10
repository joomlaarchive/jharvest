CREATE TABLE IF NOT EXISTS `#__ingest_articles` (
    `content_id` INTEGER COMMENT 'The article id.',
    `item_id` VARCHAR(128) COMMENT 'The external id of the item.',
    `created` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
    `modified` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
    PRIMARY KEY (`content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
