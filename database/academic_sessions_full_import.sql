-- ============================================================================
-- Academic Sessions (System Settings) — FULL production import.
-- One file: schema (academic_sessions table + exam_groups.academic_session_id
-- link) + role_permissions rows, so the "System Settings > Sessions" sidebar
-- item and the Examination "Academic Session" dropdown both work after import.
--
-- Safe to run more than once — every statement is guarded/idempotent.
-- Import this single file via phpMyAdmin, that's it.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. academic_sessions table
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `academic_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `added_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `academic_sessions_branch_id_foreign` (`branch_id`),
  KEY `academic_sessions_added_by_foreign` (`added_by`),
  CONSTRAINT `academic_sessions_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `academic_sessions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. exam_groups.academic_session_id — links Examination to a session ("Add
--    Exam" / "Edit Exam" Academic Session dropdown). Guarded via
--    information_schema since MySQL/MariaDB don't support
--    ADD COLUMN IF NOT EXISTS.
-- ---------------------------------------------------------------------------
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_groups' AND COLUMN_NAME = 'academic_session_id'
);

SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `exam_groups` ADD COLUMN `academic_session_id` bigint(20) unsigned DEFAULT NULL AFTER `branch_id`, ADD KEY `exam_groups_academic_session_id_foreign` (`academic_session_id`), ADD CONSTRAINT `exam_groups_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 3. Record both migrations as already run, so `php artisan migrate` on the
--    server doesn't try to (and fail on) re-applying them.
-- ---------------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`)
SELECT * FROM (
  SELECT '2026_08_11_000002_create_academic_sessions_table' AS migration, (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations`) AS batch
  UNION ALL
  SELECT '2026_08_11_000003_add_academic_session_id_to_exam_groups_table', (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations`)
) AS seed
WHERE NOT EXISTS (
  SELECT 1 FROM `migrations` existing WHERE existing.migration = seed.migration
);

-- ---------------------------------------------------------------------------
-- 4. role_permissions — without these rows the "System Settings > Sessions"
--    sidebar item stays hidden even after code + schema are deployed
--    (Sidebar.tsx fails closed for any module a role has no row for).
--    Roles: 1 Super Admin, 2 Admin, 3 Branch Admin — full access.
-- ---------------------------------------------------------------------------
INSERT INTO `role_permissions` (`role_id`, `module_slug`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`, `updated_at`)
SELECT * FROM (
  SELECT 1 AS role_id, 'academic_sessions' AS module_slug, 1 AS can_view, 1 AS can_create, 1 AS can_edit, 1 AS can_delete, NOW() AS created_at, NOW() AS updated_at
  UNION ALL SELECT 2, 'academic_sessions', 1, 1, 1, 1, NOW(), NOW()
  UNION ALL SELECT 3, 'academic_sessions', 1, 1, 1, 1, NOW(), NOW()
) AS seed
WHERE NOT EXISTS (
  SELECT 1 FROM `role_permissions` existing
  WHERE existing.role_id = seed.role_id AND existing.module_slug = seed.module_slug
);
