CREATE TABLE customers (
  id          INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name        VARCHAR(50) NOT NULL,
  email       VARCHAR(100) NOT NULL UNIQUE,
  address     VARCHAR(255),
  phone       VARCHAR(15),
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE fridge (
  id        INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name      VARCHAR(50) NOT NULL UNIQUE,
  max_temp  NUMERIC(4,1) NOT NULL DEFAULT 8.0
);

CREATE TABLE readings (
  id           INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  fridge_id    INT NOT NULL REFERENCES fridge(id),
  temperature  NUMERIC(4,1) NOT NULL,
  humidity     NUMERIC(4,1) NOT NULL,
  recorded_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_readings_fridge_time ON readings (fridge_id, recorded_at);

CREATE TABLE fan_state (
  id          INT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
  is_on       BOOLEAN NOT NULL DEFAULT FALSE,
  updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE alerts (
  id           INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  fridge_id    INT NOT NULL REFERENCES fridge(id),
  temperature  NUMERIC(4,1) NOT NULL,
  sent_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  response     VARCHAR(7) NOT NULL DEFAULT 'pending'
               CHECK (response IN ('pending','yes','no'))
);


INSERT INTO customers (name, email, phone, address) VALUES
('John Doe',      'john.doe@example.com',      '123-456-7890', '123 Main St'),
('Jane Smith',    'jane.smith@example.com',    '098-765-4321', '456 Oak Ave'),
('Alice Johnson', 'alice.johnson@example.com', '555-555-5555', '789 Pine Rd');

INSERT INTO fridge (name, max_temp) VALUES ('Frig1', 8.0), ('Frig2', 8.0);
INSERT INTO fan_state (id, is_on) VALUES (1, FALSE);

