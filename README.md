# BikeBuddy - Bicycle Rental Management System

## 🚲 Project Overview

BikeBuddy is a comprehensive bicycle rental management system built with PHP and PostgreSQL. It allows users to browse, rent, and return bicycles, while providing administrators with full management capabilities.

## ✨ Features

### For Users:
- **User Registration & Authentication** - Secure login/signup with session management
- **Browse Bicycles** - View available bicycles with images and details
- **Rent Bicycles** - Reserve bicycles with date selection
- **Rental History** - View past and current rentals
- **Profile Management** - Update personal information
- **Password Management** - Change account password

### For Administrators:
- **User Management** - View, edit, and manage user accounts
- **Bicycle Management** - Add, edit, delete bicycles with images
- **Supplier Management** - Manage bicycle suppliers
- **Rental Management** - Oversee all rental transactions
- **Reports & Analytics** - View rental statistics and reports

## 🛠️ Technology Stack

- **Backend**: PHP 8.0+
- **Database**: PostgreSQL 13+
- **Frontend**: Bootstrap 5.1.3 (Responsive Design)
- **Security**: PDO prepared statements, input validation, CSRF protection
- **Session Management**: PHP sessions with security hardening

## 📋 Installation & Setup

### Prerequisites:
- Apache/Nginx web server
- PHP 8.0 or higher
- PostgreSQL 13 or higher
- Composer (for dependency management)

### 1. Database Setup

Run the following SQL commands in PostgreSQL:

```sql
-- Create database
CREATE DATABASE bikebuddy;

-- Create user (adjust credentials as needed)
CREATE USER bikebuddy_user WITH PASSWORD 'your_secure_password';
GRANT ALL PRIVILEGES ON DATABASE bikebuddy TO bikebuddy_user;
GRANT CREATE ON SCHEMA public TO bikebuddy_user;

-- Connect as the new user and run the schema
\c bikebuddy bikebuddy_user;

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
```

### 2. File Setup

1. **Extract project files** to your web server directory (e.g., `/var/www/html/BikeBuddy/`)

2. **Update database configuration** in `config/db.php`:
```php
<?php
$host = 'localhost';
$port = '5432';  // or your PostgreSQL port
$dbname = 'bikebuddy';
$username = 'bikebuddy_user';
$password = 'your_secure_password';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password, $options);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
```

3. **Set proper permissions**:
```bash
chmod -R 755 /path/to/BikeBuddy/
chown -R www-data:www-data /path/to/BikeBuddy/  # Adjust user as needed
```

4. **Configure web server**:
   - Point your domain/subdomain to `/path/to/BikeBuddy/public/`
   - Enable PHP for .php files
   - Set up URL rewriting if needed

## 📁 Project Structure

```
BikeBuddy/
├── config/
│   └── db.php              # Database configuration
├── public/
│   ├── index.php           # Homepage (redirects to login)
│   ├── login.php           # User login
│   ├── signup.php          # User registration
│   ├── dashboard.php       # User dashboard
│   ├── bicycles.php        # Browse bicycles
│   ├── bicycle_details.php # Individual bicycle details
│   ├── rent.php            # Rent bicycle
│   ├── return.php          # Return bicycle
│   ├── my_rentals.php      # User's rental history
│   ├── profile.php         # User profile management
│   ├── change_password.php # Password change
│   ├── logout.php          # User logout
│   ├── navbar.php          # Navigation bar
│   ├── manage_bicycles.php # Admin bicycle management
│   ├── manage_rentals.php  # Admin rental management
│   ├── manage_users.php    # Admin user management
│   ├── manage_suppliers.php # Admin supplier management
│   └── report.php          # Admin reports
└── README.md               # This documentation
```

## 🚀 Usage Guide

### For Users:

1. **Register/Login**: Create account or login with existing credentials
2. **Browse Bicycles**: View available bicycles with images and details
3. **Rent Bicycle**: Select dates and confirm rental
4. **Manage Rentals**: View history and return bicycles
5. **Update Profile**: Manage personal information

### For Administrators:

1. **Login as Admin**: Use admin credentials
2. **Manage Bicycles**: Add, edit, delete bicycles
3. **Manage Users**: View and manage user accounts
4. **Manage Suppliers**: Add and manage bicycle suppliers
5. **View Reports**: Access rental statistics

## 🔒 Security Features

- **PDO Prepared Statements** - Prevents SQL injection
- **Input Validation** - Sanitizes user inputs
- **Session Security** - Secure session handling
- **Password Hashing** - bcrypt for password storage
- **CSRF Protection** - Token-based form validation
- **Error Logging** - Comprehensive error tracking

## 🛠️ Troubleshooting

### Common Issues:

**Database Connection Errors:**
- Verify PostgreSQL is running
- Check database credentials in `config/db.php`
- Ensure user has proper permissions

**Permission Errors:**
- Grant CREATE permissions to database user
- Check file permissions on web server

**Image Loading Issues:**
- Ensure image URLs are accessible
- Check network connectivity
- Verify image formats are supported

**Login Issues:**
- Clear browser cookies/cache
- Verify username/password case sensitivity
- Check PHP session configuration

## 📊 Database Schema

### Tables Overview:

- **supplier**: Bicycle suppliers
- **category**: Bicycle categories
- **bicycle**: Bicycle inventory
- **app_user**: User accounts
- **rental**: Rental transactions
- **payment**: Payment records

### Key Relationships:

- Users can have multiple rentals
- Bicycles can be rented multiple times
- Rentals link users and bicycles
- Payments are associated with rentals

## 🔄 Backup & Maintenance

### Database Backup:
```bash
pg_dump -U bikebuddy_user -h localhost bikebuddy > bikebuddy_backup.sql
```

### Regular Maintenance:
- Monitor disk space for image uploads
- Review PHP error logs regularly
- Update PHP and PostgreSQL versions
- Regular security audits

## 📞 Support

For issues or questions:
1. Check this documentation first
2. Review PHP error logs
3. Verify database connectivity
4. Check web server configuration

## 🚀 Future Enhancements

Potential features to add:
- Email notifications for rentals
- Advanced search and filtering
- Mobile app integration
- Payment gateway integration
- Multi-language support
- Advanced reporting dashboard

---

**BikeBuddy v1.0 - Bicycle Rental Management System**
*Built with PHP, PostgreSQL, and Bootstrap*
