CREATE TABLE IF NOT EXISTS `#__ingested_articles` (
    `content_id` INTEGER COMMENT 'The article id.',
    `item_id` VARCHAR(128) COMMENT 'The external id of the item.',
    PRIMARY KEY (`content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
