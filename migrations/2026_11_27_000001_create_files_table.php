<?php
declare(strict_types=1);

use Core\Database;

return new class{
	public function up(Database $db): void
	{
		$db->raw(match($db->driver()){
			'mysql' => 'CREATE TABLE files(
				id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				kind VARCHAR(20) NOT NULL,
				scope VARCHAR(50) NOT NULL,
				owner_id BIGINT UNSIGNED DEFAULT NULL,
				path VARCHAR(512) NOT NULL,
				original_name VARCHAR(255) NOT NULL,
				mime VARCHAR(50) NOT NULL,
				size INT UNSIGNED NOT NULL,
				width INT DEFAULT NULL,
				height INT DEFAULT NULL,
				checksum CHAR(64) NOT NULL,
				uploaded_by BIGINT UNSIGNED NOT NULL,
				created_at DATETIME NOT NULL,
				FOREIGN KEY (uploaded_by) REFERENCES users(id)
			)',
			'pgsql' => 'CREATE TABLE files(
				id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
				kind VARCHAR(20) NOT NULL,
				scope VARCHAR(50) NOT NULL,
				owner_id BIGINT DEFAULT NULL,
				path VARCHAR(512) NOT NULL,
				original_name VARCHAR(255) NOT NULL,
				mime VARCHAR(50) NOT NULL,
				size INT NOT NULL,
				width INT DEFAULT NULL,
				height INT DEFAULT NULL,
				checksum CHAR(64) NOT NULL,
				uploaded_by BIGINT NOT NULL,
				created_at TIMESTAMP NOT NULL,
				FOREIGN KEY (uploaded_by) REFERENCES users(id)
			)'
		});
	}

	public function down(Database $db): void
	{
		$db->raw('DROP TABLE IF EXISTS files');
	}
};