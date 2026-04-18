-- Indexes for common mobile API patterns on `rides` (lists, driver history, aggregates).
-- Idempotent: skips creation when index name already exists.
-- Run after core `rides` table exists.

SET @db := DATABASE();

-- Rider: ORDER BY id DESC WHERE rider_user_id = ?
SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'rides' AND INDEX_NAME = 'idx_rides_rider_user_id_sort'
);
SET @sql := IF(@idx = 0,
  'CREATE INDEX idx_rides_rider_user_id_sort ON rides (rider_user_id, id)',
  'SELECT 1');
PREPARE s1 FROM @sql; EXECUTE s1; DEALLOCATE PREPARE s1;

-- Driver: status filters + ORDER BY id (incoming ASC uses id ASC; history DESC uses id DESC — same composite helps)
SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'rides' AND INDEX_NAME = 'idx_rides_driver_rider_status_sort'
);
SET @sql := IF(@idx = 0,
  'CREATE INDEX idx_rides_driver_rider_status_sort ON rides (driver_rider_user_id, status, id)',
  'SELECT 1');
PREPARE s2 FROM @sql; EXECUTE s2; DEALLOCATE PREPARE s2;
