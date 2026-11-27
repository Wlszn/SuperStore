-- P4-25: readings from the Minew MSP01 BLE sensor (temperature, humidity, light, motion).
-- Run it in the Supabase SQL Editor after db.sql. Safe to run again.
CREATE TABLE IF NOT EXISTS ble_readings (
  id           INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  mac          VARCHAR(12)  NOT NULL CHECK (mac ~ '^[0-9a-f]{12}$'),  -- lowercase hex, no colons
  temperature  NUMERIC(4,1),                                          -- °C
  humidity     NUMERIC(4,1) CHECK (humidity BETWEEN 0 AND 100),       -- %
  illuminance  NUMERIC(8,1) CHECK (illuminance >= 0),                 -- lux
  motion       BOOLEAN,                                               -- true = motion detected
  battery      SMALLINT     CHECK (battery BETWEEN 0 AND 100),        -- %
  recorded_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_ble_readings_mac_time ON ble_readings (mac, recorded_at DESC);

ALTER TABLE ble_readings ENABLE ROW LEVEL SECURITY;