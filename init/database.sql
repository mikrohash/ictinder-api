# This SQL script initializes the database by creating all required tables.

CREATE TABLE `account` (`id` INT NOT NULL AUTO_INCREMENT , `discord_id` CHAR(20) NOT NULL , `pw_bcrypt` CHAR(60) NOT NULL , PRIMARY KEY (`id`)) ENGINE = MyISAM;