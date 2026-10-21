-- Reign Fashion Empire — database schema
-- Applied by backend/install.php. Safe to re-run (IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS enquiries (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref             VARCHAR(20)  NOT NULL UNIQUE,          -- customer-facing reference, e.g. RF-2610-0007
  name            VARCHAR(120) NOT NULL,
  email           VARCHAR(200) NOT NULL,
  phone           VARCHAR(40)  NULL,
  contact_method  ENUM('email','phone','whatsapp') NOT NULL,
  service         VARCHAR(40)  NOT NULL,
  event_date      DATE         NULL,
  message         TEXT         NOT NULL,
  status          ENUM('new','contacted','closed') NOT NULL DEFAULT 'new',
  admin_notes     TEXT         NULL,
  consent_at      DATETIME     NOT NULL,
  ip_hash         CHAR(64)     NOT NULL,                  -- salted hash, used only for rate limiting
  user_agent      VARCHAR(255) NULL,
  email_sent      TINYINT(1)   NOT NULL DEFAULT 0,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status_created (status, created_at),
  INDEX idx_ip_created (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enquiry_files (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  enquiry_id     INT UNSIGNED NOT NULL,
  stored_path    VARCHAR(255) NOT NULL,                   -- relative to backend/storage/uploads
  original_name  VARCHAR(255) NOT NULL,
  mime           VARCHAR(50)  NOT NULL,
  size_bytes     INT UNSIGNED NOT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_files_enquiry FOREIGN KEY (enquiry_id) REFERENCES enquiries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email          VARCHAR(200) NOT NULL UNIQUE,
  name           VARCHAR(120) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  last_login_at  DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed admin logins, for lock-out after repeated wrong passwords
CREATE TABLE IF NOT EXISTS login_attempts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_hash       CHAR(64)     NOT NULL,
  email         VARCHAR(200) NOT NULL,
  attempted_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip_hash, attempted_at),
  INDEX idx_email_time (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Editable site settings (contact details, social links, Academy info)
CREATE TABLE IF NOT EXISTS settings (
  `key`       VARCHAR(60)  NOT NULL PRIMARY KEY,
  `value`     TEXT         NOT NULL,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gallery ("Selected Creations"), managed from admin/gallery.php
CREATE TABLE IF NOT EXISTS gallery_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(120) NOT NULL,
  alt         VARCHAR(250) NOT NULL,
  category    ENUM('bridal','aso-ebi','ready-to-wear','kids','men') NOT NULL,
  size        ENUM('standard','tall','wide') NOT NULL DEFAULT 'standard',
  src         VARCHAR(255) NOT NULL,                     -- path relative to the site root
  width       SMALLINT UNSIGNED NOT NULL,
  height      SMALLINT UNSIGNED NOT NULL,
  focus       VARCHAR(20)  NOT NULL DEFAULT '50% 30%',   -- CSS object-position for cropping
  is_sample   TINYINT(1)   NOT NULL DEFAULT 0,           -- shows the "Sample image" tag
  is_visible  TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_visible_order (is_visible, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Client testimonials, managed from admin/testimonials.php.
-- Only genuine feedback the client agreed to share may be published.
CREATE TABLE IF NOT EXISTS testimonials (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quote              TEXT         NOT NULL,
  client_name        VARCHAR(80)  NOT NULL,
  service            VARCHAR(40)  NOT NULL,
  consent_confirmed  TINYINT(1)   NOT NULL,                -- owner confirmed the client gave permission
  is_visible         TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order         INT          NOT NULL DEFAULT 0,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_visible_order (is_visible, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Academy registrations from the home page form, managed in admin/academy.php
CREATE TABLE IF NOT EXISTS academy_registrations (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ref             VARCHAR(20)  NOT NULL UNIQUE,          -- e.g. RA-2610-0003
  name            VARCHAR(120) NOT NULL,
  email           VARCHAR(200) NOT NULL,
  phone           VARCHAR(40)  NOT NULL,
  level           ENUM('beginner','intermediate','advanced','professional') NOT NULL,
  schedule        ENUM('weekdays','weekends','either') NOT NULL,
  experience      TEXT         NULL,
  status          ENUM('new','contacted','enrolled','closed') NOT NULL DEFAULT 'new',
  admin_notes     TEXT         NULL,
  consent_at      DATETIME     NOT NULL,
  ip_hash         CHAR(64)     NOT NULL,
  email_sent      TINYINT(1)   NOT NULL DEFAULT 0,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_status_created (status, created_at),
  INDEX idx_ip_created (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Design videos ("In Motion": clients wearing Reign Fashion designs), managed in admin/videos.php.
-- Also created automatically on first use (backend/videos.php), so the live site needs no manual step.
CREATE TABLE IF NOT EXISTS design_videos (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category           VARCHAR(20)  NOT NULL,                   -- gallery category: bridal, aso-ebi, ready-to-wear, kids, men
  caption            VARCHAR(200) NOT NULL DEFAULT '',
  video_path         VARCHAR(255) NOT NULL,                   -- relative to the site root, assets/uploads/videos/…
  poster_path        VARCHAR(255) NULL,                       -- cover image grabbed from the video in the admin
  mime               VARCHAR(40)  NOT NULL,
  size_bytes         INT UNSIGNED NOT NULL,
  duration_seconds   SMALLINT UNSIGNED NULL,
  consent_confirmed  TINYINT(1)   NOT NULL,                   -- owner confirmed the client gave permission
  is_visible         TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order         INT          NOT NULL DEFAULT 0,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_visible_order (is_visible, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
