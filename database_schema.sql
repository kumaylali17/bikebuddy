--- ===================================================================
-- BikeBuddy Final Database Schema
-- ===================================================================

DROP TABLE IF EXISTS payment CASCADE;
DROP TABLE IF EXISTS purchase CASCADE;
DROP TABLE IF EXISTS rental CASCADE;
DROP TABLE IF EXISTS bicycle CASCADE;
DROP TABLE IF EXISTS app_user CASCADE;
DROP TABLE IF EXISTS category CASCADE;
DROP TABLE IF EXISTS supplier CASCADE;
DROP TABLE IF EXISTS branch CASCADE;

-- ===================================================================
-- 1. Core Tables (No Dependencies)
-- ===================================================================

CREATE TABLE IF NOT EXISTS branch (
branch_id SERIAL PRIMARY KEY,
name VARCHAR(100) NOT NULL,
location VARCHAR(255),
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS supplier (
supplier_id SERIAL PRIMARY KEY,
supplier_name VARCHAR(255) NOT NULL,
contact_info TEXT,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS category (
category_id SERIAL PRIMARY KEY,
name VARCHAR(100) NOT NULL,
description TEXT,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ===================================================================
-- 2. Tables with Dependencies
-- ===================================================================

CREATE TABLE IF NOT EXISTS app_user (
user_id SERIAL PRIMARY KEY,
username VARCHAR(50) UNIQUE NOT NULL,
email VARCHAR(100) UNIQUE NOT NULL,
password VARCHAR(255) NOT NULL,
phone VARCHAR(20),
address TEXT,

role VARCHAR(30) NOT NULL DEFAULT 'customer' 
    CHECK (role IN ('customer', 'branch_manager', 'purchasing_manager', 'admin')),

branch_id INTEGER REFERENCES branch(branch_id) ON DELETE SET NULL,

created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
last_login TIMESTAMP


);

CREATE TABLE IF NOT EXISTS bicycle (
bicycle_id SERIAL PRIMARY KEY,
name VARCHAR(255) NOT NULL,
description TEXT,
price_per_day DECIMAL(10,2) NOT NULL,
status VARCHAR(20) DEFAULT 'available'
CHECK (status IN ('available', 'rented', 'maintenance', 'unavailable')),
image_url TEXT,

category_id INTEGER REFERENCES category(category_id) ON DELETE SET NULL,
branch_id INTEGER REFERENCES branch(branch_id) ON DELETE CASCADE,
supplier_id INTEGER REFERENCES supplier(supplier_id) ON DELETE SET NULL,

created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP


);

CREATE TABLE IF NOT EXISTS rental (
rental_id SERIAL PRIMARY KEY,
user_id INTEGER REFERENCES app_user(user_id) ON DELETE SET NULL,
bicycle_id INTEGER REFERENCES bicycle(bicycle_id) ON DELETE SET NULL,

start_branch_id INTEGER REFERENCES branch(branch_id) ON DELETE SET NULL,
end_branch_id INTEGER REFERENCES branch(branch_id) ON DELETE SET NULL,

start_date TIMESTAMP NOT NULL,
end_date TIMESTAMP,
return_date TIMESTAMP,

total_cost DECIMAL(10,2),
status VARCHAR(20) DEFAULT 'active' 
    CHECK (status IN ('active', 'completed', 'cancelled')),
    
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP


);

CREATE TABLE IF NOT EXISTS purchase (
purchase_id SERIAL PRIMARY KEY,
supplier_id INTEGER REFERENCES supplier(supplier_id) ON DELETE SET NULL,
branch_id INTEGER REFERENCES branch(branch_id) ON DELETE SET NULL,
bicycle_id INTEGER REFERENCES bicycle(bicycle_id) ON DELETE SET NULL,
quantity INTEGER DEFAULT 1,
cost DECIMAL(10,2) NOT NULL,
purchase_date DATE NOT NULL,
status VARCHAR(20) DEFAULT 'completed'
CHECK (status IN ('pending', 'completed', 'cancelled')),
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payment (
payment_id SERIAL PRIMARY KEY,
rental_id INTEGER REFERENCES rental(rental_id) ON DELETE CASCADE,
amount DECIMAL(10,2) NOT NULL,
payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
payment_method VARCHAR(50) DEFAULT 'cash',
status VARCHAR(20) DEFAULT 'completed'
CHECK (status IN ('pending', 'completed', 'failed')),
transaction_id VARCHAR(100)
);

-- ===================================================================
-- 3. Indexes for Performance
-- ===================================================================
CREATE INDEX IF NOT EXISTS idx_bicycle_status ON bicycle(status);
CREATE INDEX IF NOT EXISTS idx_bicycle_branch_id ON bicycle(branch_id);
CREATE INDEX IF NOT EXISTS idx_rental_user_id ON rental(user_id);
CREATE INDEX IF NOT EXISTS idx_rental_status ON rental(status);
CREATE INDEX IF NOT EXISTS idx_app_user_username ON app_user(username);
CREATE INDEX IF NOT EXISTS idx_app_user_email ON app_user(email);
CREATE INDEX IF NOT EXISTS idx_app_user_role ON app_user(role);
