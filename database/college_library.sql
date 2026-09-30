CREATE TABLE IF NOT EXISTS users (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	username VARCHAR(100) NOT NULL,
	password VARCHAR(255) NOT NULL,
	full_name VARCHAR(150) NOT NULL,
	email VARCHAR(255) DEFAULT NULL,
	phone VARCHAR(30) DEFAULT NULL,
	role VARCHAR(30) NOT NULL,
	department VARCHAR(100) DEFAULT NULL,
	status VARCHAR(20) NOT NULL DEFAULT 'active',
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_users_username (username),
	KEY idx_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS categories (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	name VARCHAR(150) NOT NULL,
	description TEXT DEFAULT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS user_preferences (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id INT UNSIGNED NOT NULL,
	category_id INT UNSIGNED NOT NULL,
	preference_score INT NOT NULL DEFAULT 1,
	PRIMARY KEY (id),
	UNIQUE KEY uq_user_preferences_user_category (user_id, category_id),
	KEY idx_user_preferences_category (category_id),
	CONSTRAINT fk_user_preferences_user
		FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_user_preferences_category
		FOREIGN KEY (category_id) REFERENCES categories (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS books (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	title VARCHAR(255) NOT NULL,
	author VARCHAR(255) NOT NULL,
	isbn VARCHAR(32) DEFAULT NULL,
	category_id INT UNSIGNED DEFAULT NULL,
	summary TEXT DEFAULT NULL,
	publisher VARCHAR(255) DEFAULT NULL,
	publication_year SMALLINT UNSIGNED DEFAULT NULL,
	cover_image VARCHAR(255) DEFAULT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_books_isbn (isbn),
	KEY idx_books_category (category_id),
	KEY idx_books_title (title),
	CONSTRAINT fk_books_category
		FOREIGN KEY (category_id) REFERENCES categories (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS book_copies (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	book_id INT UNSIGNED NOT NULL,
	accession_number VARCHAR(100) NOT NULL,
	shelf_location VARCHAR(100) DEFAULT NULL,
	status VARCHAR(20) NOT NULL DEFAULT 'available',
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_book_copies_accession (accession_number),
	KEY idx_book_copies_book_status (book_id, status),
	CONSTRAINT fk_book_copies_book
		FOREIGN KEY (book_id) REFERENCES books (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS loans (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id INT UNSIGNED NOT NULL,
	copy_id INT UNSIGNED NOT NULL,
	issue_date DATE NOT NULL,
	due_date DATE NOT NULL,
	return_date DATE DEFAULT NULL,
	status VARCHAR(20) NOT NULL DEFAULT 'issued',
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_loans_user_status (user_id, status),
	KEY idx_loans_copy_status (copy_id, status),
	KEY idx_loans_due_status (due_date, status),
	CONSTRAINT fk_loans_user
		FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE RESTRICT ON UPDATE CASCADE,
	CONSTRAINT fk_loans_copy
		FOREIGN KEY (copy_id) REFERENCES book_copies (id)
		ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS reservations (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id INT UNSIGNED NOT NULL,
	book_id INT UNSIGNED NOT NULL,
	loan_id INT UNSIGNED DEFAULT NULL,
	status VARCHAR(20) NOT NULL DEFAULT 'pending',
	reserved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	KEY idx_reservations_user_status (user_id, status),
	KEY idx_reservations_book_status_time (book_id, status, reserved_at),
	KEY idx_reservations_loan (loan_id),
	CONSTRAINT fk_reservations_user
		FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE RESTRICT ON UPDATE CASCADE,
	CONSTRAINT fk_reservations_book
		FOREIGN KEY (book_id) REFERENCES books (id)
		ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_reservations_loan
		FOREIGN KEY (loan_id) REFERENCES loans (id)
		ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS user_activity (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id INT UNSIGNED NOT NULL,
	activity_date DATE NOT NULL,
	activity_type VARCHAR(50) NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uq_user_activity_day (user_id, activity_date),
	CONSTRAINT fk_user_activity_user
		FOREIGN KEY (user_id) REFERENCES users (id)
		ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
