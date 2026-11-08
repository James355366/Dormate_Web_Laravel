-- Fresh database import only. After import run: php artisan dormate:adopt-existing
-- Then run: php artisan migrate (adds mobile sign-in columns without removing data).
-- Select a NEW empty MySQL/MariaDB database in phpMyAdmin before importing.
-- This file does not change the selected database.
-- DorMate • MySQL 8 / MariaDB 10.4+ • Import into a NEW database.
CREATE TABLE dorms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    address VARCHAR(255) NOT NULL DEFAULT '',
    owner_id INT UNSIGNED NULL,
    gcash_name VARCHAR(100) NOT NULL DEFAULT '',
    gcash_number VARCHAR(20) NOT NULL DEFAULT '',
    gcash_qr VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dorm_id INT UNSIGNED NOT NULL,
    room_number VARCHAR(20) NOT NULL,
    capacity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    monthly_rent DECIMAL(10, 2) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_room (dorm_id, room_number),
    CONSTRAINT fk_room_dorm FOREIGN KEY (dorm_id) REFERENCES dorms (id),
    CHECK (capacity > 0),
    CHECK (monthly_rent >= 0)
) ENGINE = InnoDB;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM(
        'RESIDENT',
        'DORM_ADMIN',
        'MAINTENANCE_STAFF',
        'SUPER_ADMIN'
    ) NOT NULL DEFAULT 'RESIDENT',
    status ENUM(
        'pending',
        'active',
        'disabled',
        'rejected'
    ) NOT NULL DEFAULT 'pending',
    dorm_id INT UNSIGNED NULL,
    room_id INT UNSIGNED NULL,
    requested_room VARCHAR(20) NOT NULL DEFAULT '',
    phone VARCHAR(20) NOT NULL DEFAULT '',
    avatar_path VARCHAR(255) NULL,
    session_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email (email),
    KEY ix_user_dorm_role (dorm_id, role, status),
    CONSTRAINT fk_user_dorm FOREIGN KEY (dorm_id) REFERENCES dorms (id),
    CONSTRAINT fk_user_room FOREIGN KEY (room_id) REFERENCES rooms (id)
) ENGINE = InnoDB;

ALTER TABLE dorms
ADD CONSTRAINT fk_dorm_owner FOREIGN KEY (owner_id) REFERENCES users (id);

CREATE TABLE bills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dorm_id INT UNSIGNED NOT NULL,
    resident_id INT UNSIGNED NOT NULL,
    room_id INT UNSIGNED NOT NULL,
    billing_month DATE NOT NULL,
    room_rent DECIMAL(10, 2) NOT NULL,
    electricity DECIMAL(10, 2) NOT NULL DEFAULT 0,
    water DECIMAL(10, 2) NOT NULL DEFAULT 0,
    wifi DECIMAL(10, 2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(10, 2) NOT NULL,
    due_date DATE NOT NULL,
    status ENUM(
        'unpaid',
        'pending',
        'paid',
        'rejected'
    ) NOT NULL DEFAULT 'unpaid',
    payment_method ENUM('GCASH', 'CASH') NULL,
    reference_number VARCHAR(40) NULL,
    receipt_path VARCHAR(255) NULL,
    submitted_at DATETIME NULL,
    paid_amount DECIMAL(10, 2) NULL,
    paid_at DATETIME NULL,
    reviewed_by INT UNSIGNED NULL,
    rejection_reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bill_month (resident_id, billing_month),
    KEY ix_bill_dorm (dorm_id, status),
    CONSTRAINT fk_bill_dorm FOREIGN KEY (dorm_id) REFERENCES dorms (id),
    CONSTRAINT fk_bill_resident FOREIGN KEY (resident_id) REFERENCES users (id),
    CONSTRAINT fk_bill_room FOREIGN KEY (room_id) REFERENCES rooms (id),
    CONSTRAINT fk_bill_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id),
    CHECK (total_amount >= 0),
    CHECK (room_rent >= 0),
    CHECK (electricity >= 0),
    CHECK (water >= 0),
    CHECK (wifi >= 0)
) ENGINE = InnoDB;

CREATE TABLE maintenance_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dorm_id INT UNSIGNED NOT NULL,
    resident_id INT UNSIGNED NOT NULL,
    room_id INT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    photo_path VARCHAR(255) NULL,
    status ENUM(
        'PENDING',
        'IN_PROGRESS',
        'RESOLVED'
    ) NOT NULL DEFAULT 'PENDING',
    assigned_staff_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_report_staff (assigned_staff_id, status),
    KEY ix_report_dorm (dorm_id, status),
    CONSTRAINT fk_report_dorm FOREIGN KEY (dorm_id) REFERENCES dorms (id),
    CONSTRAINT fk_report_resident FOREIGN KEY (resident_id) REFERENCES users (id),
    CONSTRAINT fk_report_room FOREIGN KEY (room_id) REFERENCES rooms (id),
    CONSTRAINT fk_report_staff FOREIGN KEY (assigned_staff_id) REFERENCES users (id)
) ENGINE = InnoDB;

CREATE TABLE announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dorm_id INT UNSIGNED NULL,
    posted_by INT UNSIGNED NOT NULL,
    title VARCHAR(140) NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_post_dorm FOREIGN KEY (dorm_id) REFERENCES dorms (id),
    CONSTRAINT fk_post_user FOREIGN KEY (posted_by) REFERENCES users (id)
) ENGINE = InnoDB;

CREATE TABLE announcement_reads (
    announcement_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (announcement_id, user_id),
    CONSTRAINT fk_read_post FOREIGN KEY (announcement_id) REFERENCES announcements (id) ON DELETE CASCADE,
    CONSTRAINT fk_read_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE = InnoDB;

CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE = InnoDB;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_key CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY ix_attempt (attempt_key, created_at)
) ENGINE = InnoDB;

CREATE TABLE audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id INT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    entity_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (actor_id) REFERENCES users (id)
) ENGINE = InnoDB;

-- FICTIONAL DEMO DATA. All demo account passwords: Dormate123!
INSERT INTO
    dorms (id, name, address)
VALUES (
        1,
        'Maple House',
        'University district'
    ),
    (
        2,
        'Cedar House',
        'College avenue'
    );

INSERT INTO
    rooms (
        id,
        dorm_id,
        room_number,
        capacity,
        monthly_rent
    )
VALUES (1, 1, '101', 4, 1500),
    (2, 1, '102', 2, 1800),
    (3, 1, '103', 4, 1500),
    (4, 1, '201', 2, 2200),
    (5, 2, '101', 2, 1700);

INSERT INTO
    users (
        id,
        name,
        email,
        password_hash,
        role,
        status,
        dorm_id,
        room_id,
        requested_room
    )
VALUES (
        1,
        'Jamie Cruz',
        'super@dormate.test',
        '$2y$10$BpquIE9NxqnRNKZyUiPfxeR1UcE1ki0k2k.4WwLQP6ArCpVw1lmyK',
        'SUPER_ADMIN',
        'active',
        NULL,
        NULL,
        ''
    ),
    (
        2,
        'Alex Reyes',
        'admin@dormate.test',
        '$2y$10$BpquIE9NxqnRNKZyUiPfxeR1UcE1ki0k2k.4WwLQP6ArCpVw1lmyK',
        'DORM_ADMIN',
        'active',
        1,
        NULL,
        ''
    ),
    (
        3,
        'Sam Rivera',
        'staff@dormate.test',
        '$2y$10$BpquIE9NxqnRNKZyUiPfxeR1UcE1ki0k2k.4WwLQP6ArCpVw1lmyK',
        'MAINTENANCE_STAFF',
        'active',
        1,
        NULL,
        ''
    ),
    (
        4,
        'Casey Santos',
        'resident@dormate.test',
        '$2y$10$BpquIE9NxqnRNKZyUiPfxeR1UcE1ki0k2k.4WwLQP6ArCpVw1lmyK',
        'RESIDENT',
        'active',
        1,
        1,
        ''
    ),
    (
        5,
        'Taylor Lim',
        'resident2@dormate.test',
        '$2y$10$BpquIE9NxqnRNKZyUiPfxeR1UcE1ki0k2k.4WwLQP6ArCpVw1lmyK',
        'RESIDENT',
        'active',
        1,
        2,
        ''
    ),
    (
        6,
        'Robin Garcia',
        'pending@dormate.test',
        '$2y$10$BpquIE9NxqnRNKZyUiPfxeR1UcE1ki0k2k.4WwLQP6ArCpVw1lmyK',
        'RESIDENT',
        'pending',
        1,
        NULL,
        '103'
    ),
    (
        7,
        'Morgan Lee',
        'admin2@dormate.test',
        '$2y$10$BpquIE9NxqnRNKZyUiPfxeR1UcE1ki0k2k.4WwLQP6ArCpVw1lmyK',
        'DORM_ADMIN',
        'active',
        2,
        NULL,
        ''
    );

UPDATE dorms SET owner_id = 2 WHERE id = 1;

UPDATE dorms SET owner_id = 7 WHERE id = 2;

INSERT INTO
    bills (
        id,
        dorm_id,
        resident_id,
        room_id,
        billing_month,
        room_rent,
        electricity,
        water,
        wifi,
        total_amount,
        due_date,
        status,
        paid_amount,
        paid_at,
        payment_method
    )
VALUES (
        1,
        1,
        4,
        1,
        DATE_FORMAT(CURDATE(), '%Y-%m-01'),
        1500,
        120,
        80,
        100,
        1800,
        LAST_DAY(CURDATE()),
        'unpaid',
        NULL,
        NULL,
        NULL
    ),
    (
        2,
        1,
        5,
        2,
        DATE_FORMAT(CURDATE(), '%Y-%m-01'),
        1800,
        100,
        70,
        100,
        2070,
        LAST_DAY(CURDATE()),
        'unpaid',
        NULL,
        NULL,
        NULL
    ),
    (
        3,
        1,
        4,
        1,
        DATE_FORMAT(
            DATE_SUB(CURDATE(), INTERVAL 1 MONTH),
            '%Y-%m-01'
        ),
        1500,
        100,
        70,
        100,
        1770,
        DATE_SUB(CURDATE(), INTERVAL 1 MONTH),
        'paid',
        1770,
        DATE_SUB(NOW(), INTERVAL 20 DAY),
        'CASH'
    );

INSERT INTO
    maintenance_reports (
        id,
        dorm_id,
        resident_id,
        room_id,
        title,
        description,
        status,
        assigned_staff_id
    )
VALUES (
        1,
        1,
        4,
        1,
        'Bathroom tap is leaking',
        'The bathroom tap drips even when it is fully closed. Please check the washer.',
        'IN_PROGRESS',
        3
    ),
    (
        2,
        1,
        5,
        2,
        'Door lock needs attention',
        'The key is difficult to turn from the outside.',
        'PENDING',
        NULL
    );

INSERT INTO
    announcements (
        dorm_id,
        posted_by,
        title,
        body
    )
VALUES (
        1,
        2,
        'A little care for our shared spaces',
        'Please keep the kitchen and common areas tidy after use. Small habits make a happy home. Thank you, everyone!'
    ),
    (
        1,
        2,
        'Welcome to Maple House',
        'Your dorm updates, maintenance requests, and monthly bills are now together in DorMate. Set up your profile to get started.'
    ),
    (
        NULL,
        1,
        'Welcome to your community',
        'This is a demonstration workspace. The names, accounts, and records are fictional.'
    );