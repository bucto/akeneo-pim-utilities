CREATE TABLE IF NOT EXISTS pim_compare_links (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(200) NOT NULL,
  skus         TEXT         NOT NULL,
  family_code  VARCHAR(100) NOT NULL,
  sort_order   INT          NOT NULL DEFAULT 0,
  updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_family (family_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
