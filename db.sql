SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS users (
	`uid` CHAR(11) NOT NULL,
	`email` VARCHAR(255) NOT NULL,
	`name` VARCHAR(255) NOT NULL,
	`verified` TINYINT(1) NOT NULL DEFAULT 0,
	`master` TINYINT(1) NOT NULL DEFAULT 0,
	`admin` TINYINT(1) NOT NULL DEFAULT 0,
	`password` VARCHAR(255) NULL,
	`date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`banned` TINYINT(1) NOT NULL DEFAULT 0,
	`delete_on` DATE NULL,
	PRIMARY KEY (`uid`),
	UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_provider (
	`user_uid` CHAR(11) NOT NULL,
	`provider` VARCHAR(255) NOT NULL,
	`provider_id` VARCHAR(255) NOT NULL,
	PRIMARY KEY (`user_uid`, `provider`),
	UNIQUE KEY `uk_provider_provider_id` (`provider`, `provider_id`),
	CONSTRAINT `fk_user_provider_user_uid`
		FOREIGN KEY (`user_uid`) REFERENCES `users` (`uid`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
	`key` VARCHAR(255) NOT NULL,
	`description` VARCHAR(255) NULL,
	`value` VARCHAR(255) NOT NULL,
	PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`key`, `description`, `value`) VALUES
('google_client_id', 'google_client_id', ''),
('google_client_secret', 'google_client_secret', '');

COMMIT;