CREATE TABLE IF NOT EXISTS pim_family_config (
  family_code     VARCHAR(100) NOT NULL,
  label           VARCHAR(200) NULL,
  for_products    TINYINT(1)   NOT NULL DEFAULT 0,
  for_automation  TINYINT(1)   NOT NULL DEFAULT 0,
  for_accessories TINYINT(1)   NOT NULL DEFAULT 0,
  excluded        TINYINT(1)   NOT NULL DEFAULT 0,
  updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (family_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
