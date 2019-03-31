# This SQL script initializes the database by creating all required tables.

CREATE TABLE `account` (`id` INT NOT NULL AUTO_INCREMENT , `discord_id` CHAR(20) NOT NULL , `pw_bcrypt` CHAR(60) NOT NULL , PRIMARY KEY (`id`)) ENGINE = MyISAM;

CREATE TABLE `node` ( `id` INT NOT NULL AUTO_INCREMENT , `account` INT NOT NULL , `address` VARCHAR(255) NOT NULL , `static_nbs` INT NOT NULL , PRIMARY KEY (`id`), INDEX (`account`)) ENGINE = MyISAM;