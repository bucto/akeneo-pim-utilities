-- Migration: Stanzwerkzeuge und Abkantwerkzeuge als neue Familien-Kategorien
ALTER TABLE pim_family_config
  ADD COLUMN IF NOT EXISTS for_punching_tools TINYINT(1) NOT NULL DEFAULT 0
    AFTER for_accessories,
  ADD COLUMN IF NOT EXISTS for_bending_tools  TINYINT(1) NOT NULL DEFAULT 0
    AFTER for_punching_tools;
