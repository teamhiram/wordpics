-- WordPics — MySQL スキーマ（Xserver の phpMyAdmin で流す）
-- 実行前にデータベースは作成済みであること。
-- 文字コード: utf8mb4 / エンジン: InnoDB

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            CHAR(36) PRIMARY KEY,
  email         VARCHAR(255) NOT NULL UNIQUE,
  display_name  VARCHAR(100) DEFAULT NULL,
  is_admin      TINYINT(1) NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS magic_tokens (
  token         CHAR(64) PRIMARY KEY,
  email         VARCHAR(255) NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at    DATETIME NOT NULL,
  consumed_at   DATETIME DEFAULT NULL,
  INDEX idx_magic_tokens_email (email),
  INDEX idx_magic_tokens_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS submissions (
  id                 CHAR(36) PRIMARY KEY,
  author_user_id     CHAR(36) DEFAULT NULL,
  is_user_submission TINYINT(1) NOT NULL DEFAULT 1,
  status             ENUM('pending','approved','rejected','unpublished') NOT NULL,
  original_path      VARCHAR(512) NOT NULL,
  revised_path       VARCHAR(512) DEFAULT NULL,
  mime_type          VARCHAR(50) NOT NULL,
  book_abbr          VARCHAR(8) NOT NULL,
  chapter            INT NOT NULL,
  verse              VARCHAR(20) NOT NULL,
  verse_text         TEXT NOT NULL,
  citation_ja        VARCHAR(100) NOT NULL,
  size               ENUM('postcard','businesscard','square') NOT NULL,
  orientation        ENUM('landscape','portrait','square') NOT NULL,
  tags               JSON NOT NULL,
  notes              TEXT DEFAULT NULL,
  rejection_reason   TEXT DEFAULT NULL,
  approved_at        DATETIME DEFAULT NULL,
  approver_user_id   CHAR(36) DEFAULT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_submissions_status (status),
  INDEX idx_submissions_author (author_user_id),
  INDEX idx_submissions_book (book_abbr),
  INDEX idx_submissions_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reports (
  id                 CHAR(36) PRIMARY KEY,
  submission_id      CHAR(36) NOT NULL,
  reporter_user_id   CHAR(36) DEFAULT NULL,
  reporter_ip_hash   CHAR(64) DEFAULT NULL,
  message            TEXT NOT NULL,
  status             ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at        DATETIME DEFAULT NULL,
  INDEX idx_reports_submission (submission_id),
  INDEX idx_reports_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
