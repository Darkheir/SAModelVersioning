DROP TABLE IF EXISTS `SAMVTest_article`;
CREATE  TABLE IF NOT EXISTS `SAMVTest_article` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `version` INT ,
  `title` VARCHAR(45) NOT NULL DEFAULT '' ,
  `content` TEXT NOT NULL DEFAULT '' ,
  `approved` TINYINT(1) NOT NULL DEFAULT false ,
  `visible` TINYINT(1) NOT NULL DEFAULT false ,
  `deleted` TINYINT(1) NOT NULL DEFAULT false ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB;

DROP TABLE IF EXISTS `SAMVTest_article_version`;
CREATE  TABLE IF NOT EXISTS `SAMVTest_article_version` (
  `id` INT NOT NULL ,
  `version` INT NOT NULL AUTO_INCREMENT ,
  `title` VARCHAR(45) NOT NULL DEFAULT '' ,
  `content` TEXT NOT NULL DEFAULT '' ,
  `approved` TINYINT(1) NOT NULL DEFAULT false ,
  `visible` TINYINT(1) NOT NULL DEFAULT false ,
  `deleted` TINYINT(1) NOT NULL DEFAULT false ,
  `version_comment` VARCHAR(255) NOT NULL ,
  `created_by` VARCHAR(255) NOT NULL ,
  `created_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
  PRIMARY KEY (`version`, `id`) )
ENGINE = MyISAM;

DROP TABLE IF EXISTS `SAMVTest_comment`;
CREATE  TABLE IF NOT EXISTS `SAMVTest_comment` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `iter` INT ,
  `comment` TEXT NOT NULL DEFAULT '' ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB;


DROP TABLE IF EXISTS `SAMVTest_comment_history`;
CREATE  TABLE IF NOT EXISTS `SAMVTest_comment_history` (
  `id` INT NOT NULL ,
  `iter` INT NOT NULL AUTO_INCREMENT ,
  `comment` TEXT NOT NULL DEFAULT '' ,
  `edit_reason` VARCHAR(255) NOT NULL ,
  `user` VARCHAR(255) NOT NULL ,
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
  PRIMARY KEY (`id`, `iter`) )
ENGINE = MyISAM;


