-- Migriert bestehende pim_compare_links: url -> skus (kommagetrennte Artikelnummern)
-- Einmal ausführen, wenn die Tabelle noch die Spalte url hat.

ALTER TABLE pim_compare_links ADD COLUMN skus TEXT NULL AFTER name;

UPDATE pim_compare_links
SET skus = REPLACE(
    SUBSTRING_INDEX(SUBSTRING_INDEX(url, 'skus=', -1), '&', 1),
    '%2C', ','
)
WHERE url LIKE '%skus=%' AND (skus IS NULL OR skus = '');

ALTER TABLE pim_compare_links DROP COLUMN url;
ALTER TABLE pim_compare_links MODIFY skus TEXT NOT NULL;
