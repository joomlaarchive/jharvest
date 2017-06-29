CREATE TABLE IF NOT EXISTS `#__content_assets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `link` VARCHAR(255) NOT NULL DEFAULT '',
  `description` TEXT NOT NULL,
  `ordering` INT(11) NOT NULL DEFAULT 0,
  `content_id` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_content_id` (`content_id`))
ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
