-- BikeBuddy Database Schema
-- Run this script in PostgreSQL to set up the complete database

-- Create database (run this separately if needed)
-- CREATE DATABASE bikebuddy;

-- Connect to the database
\c bikebuddy;

-- Create tables
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

CREATE TABLE IF NOT EXISTS bicycle (
    bicycle_id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price_per_day DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'available' CHECK (status IN ('available', 'rented', 'maintenance', 'unavailable')),
    image_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS app_user (
    user_id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP
);

CREATE TABLE IF NOT EXISTS rental (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES app_user(user_id),
    bicycle_id INTEGER REFERENCES bicycle(bicycle_id),
    start_date DATE NOT NULL,
    end_date DATE,
    return_date DATE,
    total_cost DECIMAL(10,2),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'completed', 'cancelled')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payment (
    payment_id SERIAL PRIMARY KEY,
    rental_id INTEGER REFERENCES rental(id),
    amount DECIMAL(10,2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_method VARCHAR(50) DEFAULT 'cash',
    status VARCHAR(20) DEFAULT 'completed' CHECK (status IN ('pending', 'completed', 'failed')),
    transaction_id VARCHAR(100)
);

-- Insert sample data
INSERT INTO supplier (supplier_name, contact_info) VALUES
('Default Supplier', 'Contact information for default supplier');

INSERT INTO category (name, description) VALUES
('Mountain Bikes', 'Bicycles designed for off-road trails'),
('Road Bikes', 'High-speed bicycles for paved roads'),
('City Bikes', 'Comfortable bicycles for urban commuting');

INSERT INTO bicycle (name, description, price_per_day, status, image_url) VALUES
('Mountain Pro', 'High-performance mountain bike for off-road adventures', 25.00, 'available', 'https://picsum.photos/400/300?random=1'),
('Road Racer', 'Lightweight road bike for speed and efficiency', 20.00, 'available', 'https://picsum.photos/400/300?random=2'),
('City Cruiser', 'Comfortable hybrid bike for city commuting', 15.00, 'available', 'https://picsum.photos/400/300?random=3');

INSERT INTO app_user (username, email, password, is_admin) VALUES
('admin', 'admin@bikebuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', TRUE);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_bicycle_status ON bicycle(status);
CREATE INDEX IF NOT EXISTS idx_rental_user_id ON rental(user_id);
CREATE INDEX IF NOT EXISTS idx_rental_status ON rental(status);
CREATE INDEX IF NOT EXISTS idx_app_user_username ON app_user(username);
CREATE INDEX IF NOT EXISTS idx_app_user_email ON app_user(email);

-- Grant permissions (adjust username as needed)
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO bikebuddy_user;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO bikebuddy_user;

COMMIT;
