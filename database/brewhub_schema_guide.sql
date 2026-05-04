-- BrewHub Database Schema Guide
-- Updated: 2026-05-04
--
-- This file contains:
-- 1. The current schema used by the system right now
-- 2. Optional helper queries for existing data
-- 3. An optional Version 2 upgrade path for future features
--
-- Recommended usage for other developers:
-- - Use Section A to create a fresh database from scratch
-- - Use Section B only if you already have older data and want to align it
-- - Use Section C only if your team agrees to extend the schema further


-- =========================================================
-- SECTION A: CURRENT WORKING SCHEMA
-- This is the schema the app currently uses.
-- =========================================================

CREATE DATABASE IF NOT EXISTS brewhub
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE brewhub;

-- Disable foreign key checks temporarily so tables can be dropped safely
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS seller_profiles;
DROP TABLE IF EXISTS seller_requests;
DROP TABLE IF EXISTS listings;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;


-- ---------------------------------------------------------
-- users
-- Stores all user accounts.
-- role:
-- - buyer  = can shop
-- - seller = legacy seller-only role
-- - admin  = admin access
-- - both   = can shop and sell
-- ---------------------------------------------------------
CREATE TABLE users (
  user_id INT(11) NOT NULL AUTO_INCREMENT,
  FirstName VARCHAR(100) DEFAULT NULL,
  LastName VARCHAR(100) DEFAULT NULL,
  username VARCHAR(100) DEFAULT NULL,
  email VARCHAR(100) DEFAULT NULL,
  password VARCHAR(255) DEFAULT NULL,
  reset_code INT(10) DEFAULT NULL,
  role ENUM('buyer','seller','admin','both') DEFAULT 'buyer',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------
-- products
-- Stores product master data.
-- image_path was added so uploaded product images can be saved.
-- ---------------------------------------------------------
CREATE TABLE products (
  product_id INT(11) NOT NULL AUTO_INCREMENT,
  product_name VARCHAR(100) DEFAULT NULL,
  category VARCHAR(100) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------
-- listings
-- Connects a seller (user_id) to a product and stores selling data.
-- price and stock live here because different sellers may list products
-- differently in future expansions.
-- ---------------------------------------------------------
CREATE TABLE listings (
  listing_id INT(11) NOT NULL AUTO_INCREMENT,
  user_id INT(11) NOT NULL,
  product_id INT(11) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  stock INT(11) DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (listing_id),
  KEY user_id (user_id),
  KEY product_id (product_id),
  CONSTRAINT listings_ibfk_1
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE CASCADE,
  CONSTRAINT listings_ibfk_2
    FOREIGN KEY (product_id) REFERENCES products (product_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------
-- seller_requests
-- Stores seller application requests before approval.
-- ---------------------------------------------------------
CREATE TABLE seller_requests (
  request_id INT(11) NOT NULL AUTO_INCREMENT,
  user_id INT(11) NOT NULL,
  first_name VARCHAR(100) DEFAULT NULL,
  last_name VARCHAR(100) DEFAULT NULL,
  email VARCHAR(100) DEFAULT NULL,
  contact VARCHAR(20) DEFAULT NULL,
  shop_name VARCHAR(100) DEFAULT NULL,
  seller_type VARCHAR(50) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  address TEXT DEFAULT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (request_id),
  KEY user_id (user_id),
  CONSTRAINT seller_requests_ibfk_1
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------
-- seller_profiles
-- Stores active seller shop information after approval.
-- This is what seller pages should read for shop info.
-- ---------------------------------------------------------
CREATE TABLE seller_profiles (
  seller_profile_id INT(11) NOT NULL AUTO_INCREMENT,
  user_id INT(11) NOT NULL,
  shop_name VARCHAR(100) NOT NULL,
  contact VARCHAR(20) DEFAULT NULL,
  seller_type VARCHAR(50) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  address TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (seller_profile_id),
  UNIQUE KEY unique_seller_user (user_id),
  CONSTRAINT seller_profiles_user_fk
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------
-- cart_items
-- Stores the shopping cart in the database.
-- Unique key ensures one row per buyer per listing.
-- ---------------------------------------------------------
CREATE TABLE cart_items (
  cart_item_id INT(11) NOT NULL AUTO_INCREMENT,
  buyer_id INT(11) NOT NULL,
  listing_id INT(11) NOT NULL,
  quantity INT(11) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (cart_item_id),
  UNIQUE KEY unique_buyer_listing (buyer_id, listing_id),
  KEY buyer_id (buyer_id),
  KEY listing_id (listing_id),
  CONSTRAINT cart_items_buyer_fk
    FOREIGN KEY (buyer_id) REFERENCES users (user_id)
    ON DELETE CASCADE,
  CONSTRAINT cart_items_listing_fk
    FOREIGN KEY (listing_id) REFERENCES listings (listing_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------
-- orders
-- Stores order headers.
-- Note: current system does NOT yet store checkout phone, address,
-- or payment method in this table.
-- ---------------------------------------------------------
CREATE TABLE orders (
  order_id INT(11) NOT NULL AUTO_INCREMENT,
  buyer_id INT(11) NOT NULL,
  total_amount DECIMAL(10,2) DEFAULT NULL,
  status ENUM('pending','completed','cancelled') DEFAULT 'pending',
  order_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (order_id),
  KEY buyer_id (buyer_id),
  CONSTRAINT orders_ibfk_1
    FOREIGN KEY (buyer_id) REFERENCES users (user_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ---------------------------------------------------------
-- order_items
-- Stores individual items per order.
-- ---------------------------------------------------------
CREATE TABLE order_items (
  order_item_id INT(11) NOT NULL AUTO_INCREMENT,
  order_id INT(11) NOT NULL,
  listing_id INT(11) NOT NULL,
  quantity INT(11) NOT NULL,
  subtotal DECIMAL(10,2) DEFAULT NULL,
  PRIMARY KEY (order_item_id),
  KEY order_id (order_id),
  KEY listing_id (listing_id),
  CONSTRAINT order_items_ibfk_1
    FOREIGN KEY (order_id) REFERENCES orders (order_id)
    ON DELETE CASCADE,
  CONSTRAINT order_items_ibfk_2
    FOREIGN KEY (listing_id) REFERENCES listings (listing_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- =========================================================
-- SECTION B: OPTIONAL DATA ALIGNMENT QUERIES
-- Run these only if you already have existing data.
-- =========================================================

-- ---------------------------------------------------------
-- B1. Convert legacy seller-only users into both-role users
-- so they can sell and shop.
-- ---------------------------------------------------------
UPDATE users
SET role = 'both'
WHERE role = 'seller';


-- ---------------------------------------------------------
-- B2. Copy approved seller request details into seller_profiles
-- This is useful if older data exists before seller_profiles
-- was introduced.
-- ---------------------------------------------------------
INSERT INTO seller_profiles (
  user_id,
  shop_name,
  contact,
  seller_type,
  description,
  address
)
SELECT
  sr.user_id,
  sr.shop_name,
  sr.contact,
  sr.seller_type,
  sr.description,
  sr.address
FROM seller_requests sr
WHERE sr.status = 'approved'
ON DUPLICATE KEY UPDATE
  shop_name = VALUES(shop_name),
  contact = VALUES(contact),
  seller_type = VALUES(seller_type),
  description = VALUES(description),
  address = VALUES(address);


-- =========================================================
-- SECTION C: VERSION 2 OPTIONAL UPGRADE
-- This is NOT required by the current codebase.
-- Use this only if the team wants more complete checkout data
-- and better shop-browsing support.
-- =========================================================

-- ---------------------------------------------------------
-- C1. Add checkout details directly to orders
-- Use this if you want the system to save:
-- - customer name
-- - phone number
-- - delivery address
-- - payment method
-- ---------------------------------------------------------
ALTER TABLE orders
  ADD COLUMN customer_name VARCHAR(150) NULL AFTER buyer_id,
  ADD COLUMN customer_phone VARCHAR(30) NULL AFTER customer_name,
  ADD COLUMN customer_address TEXT NULL AFTER customer_phone,
  ADD COLUMN payment_method ENUM('cod','online') NULL AFTER customer_address;


-- ---------------------------------------------------------
-- C2. Optional shop browsing helper view
-- This gives developers a clean way to query shops with seller info.
-- ---------------------------------------------------------
CREATE OR REPLACE VIEW vw_shops AS
SELECT
  u.user_id,
  u.username,
  u.email,
  u.role,
  sp.shop_name,
  sp.contact,
  sp.seller_type,
  sp.description,
  sp.address,
  sp.created_at,
  sp.updated_at
FROM users u
JOIN seller_profiles sp ON sp.user_id = u.user_id
WHERE u.role IN ('seller', 'both');


-- ---------------------------------------------------------
-- C3. Example query for a shop page
-- Replace 1 with the seller user_id you want to inspect.
-- ---------------------------------------------------------
-- SELECT
--   l.listing_id,
--   p.product_name,
--   p.category,
--   p.description,
--   p.image_path,
--   l.price,
--   l.stock
-- FROM listings l
-- JOIN products p ON l.product_id = p.product_id
-- WHERE l.user_id = 1
-- ORDER BY l.created_at DESC;


-- =========================================================
-- SECTION D: WHOLE QUERY (CLEAN COPY)
-- Current working schema with no comments
-- =========================================================

CREATE DATABASE IF NOT EXISTS brewhub
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE brewhub;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart_items;
DROP TABLE IF EXISTS seller_profiles;
DROP TABLE IF EXISTS seller_requests;
DROP TABLE IF EXISTS listings;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  user_id INT(11) NOT NULL AUTO_INCREMENT,
  FirstName VARCHAR(100) DEFAULT NULL,
  LastName VARCHAR(100) DEFAULT NULL,
  username VARCHAR(100) DEFAULT NULL,
  email VARCHAR(100) DEFAULT NULL,
  password VARCHAR(255) DEFAULT NULL,
  reset_code INT(10) DEFAULT NULL,
  role ENUM('buyer','seller','admin','both') DEFAULT 'buyer',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE products (
  product_id INT(11) NOT NULL AUTO_INCREMENT,
  product_name VARCHAR(100) DEFAULT NULL,
  category VARCHAR(100) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE listings (
  listing_id INT(11) NOT NULL AUTO_INCREMENT,
  user_id INT(11) NOT NULL,
  product_id INT(11) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  stock INT(11) DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (listing_id),
  KEY user_id (user_id),
  KEY product_id (product_id),
  CONSTRAINT listings_ibfk_1
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE CASCADE,
  CONSTRAINT listings_ibfk_2
    FOREIGN KEY (product_id) REFERENCES products (product_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE seller_requests (
  request_id INT(11) NOT NULL AUTO_INCREMENT,
  user_id INT(11) NOT NULL,
  first_name VARCHAR(100) DEFAULT NULL,
  last_name VARCHAR(100) DEFAULT NULL,
  email VARCHAR(100) DEFAULT NULL,
  contact VARCHAR(20) DEFAULT NULL,
  shop_name VARCHAR(100) DEFAULT NULL,
  seller_type VARCHAR(50) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  address TEXT DEFAULT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (request_id),
  KEY user_id (user_id),
  CONSTRAINT seller_requests_ibfk_1
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE seller_profiles (
  seller_profile_id INT(11) NOT NULL AUTO_INCREMENT,
  user_id INT(11) NOT NULL,
  shop_name VARCHAR(100) NOT NULL,
  contact VARCHAR(20) DEFAULT NULL,
  seller_type VARCHAR(50) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  address TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (seller_profile_id),
  UNIQUE KEY unique_seller_user (user_id),
  CONSTRAINT seller_profiles_user_fk
    FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE cart_items (
  cart_item_id INT(11) NOT NULL AUTO_INCREMENT,
  buyer_id INT(11) NOT NULL,
  listing_id INT(11) NOT NULL,
  quantity INT(11) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (cart_item_id),
  UNIQUE KEY unique_buyer_listing (buyer_id, listing_id),
  KEY buyer_id (buyer_id),
  KEY listing_id (listing_id),
  CONSTRAINT cart_items_buyer_fk
    FOREIGN KEY (buyer_id) REFERENCES users (user_id)
    ON DELETE CASCADE,
  CONSTRAINT cart_items_listing_fk
    FOREIGN KEY (listing_id) REFERENCES listings (listing_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE orders (
  order_id INT(11) NOT NULL AUTO_INCREMENT,
  buyer_id INT(11) NOT NULL,
  total_amount DECIMAL(10,2) DEFAULT NULL,
  status ENUM('pending','completed','cancelled') DEFAULT 'pending',
  order_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (order_id),
  KEY buyer_id (buyer_id),
  CONSTRAINT orders_ibfk_1
    FOREIGN KEY (buyer_id) REFERENCES users (user_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE order_items (
  order_item_id INT(11) NOT NULL AUTO_INCREMENT,
  order_id INT(11) NOT NULL,
  listing_id INT(11) NOT NULL,
  quantity INT(11) NOT NULL,
  subtotal DECIMAL(10,2) DEFAULT NULL,
  PRIMARY KEY (order_item_id),
  KEY order_id (order_id),
  KEY listing_id (listing_id),
  CONSTRAINT order_items_ibfk_1
    FOREIGN KEY (order_id) REFERENCES orders (order_id)
    ON DELETE CASCADE,
  CONSTRAINT order_items_ibfk_2
    FOREIGN KEY (listing_id) REFERENCES listings (listing_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =========================================================
-- SECTION E: WHOLE QUERY V2 (CLEAN COPY)
-- Optional future upgrade with extra checkout fields and shop view
-- =========================================================

ALTER TABLE orders
  ADD COLUMN customer_name VARCHAR(150) NULL AFTER buyer_id,
  ADD COLUMN customer_phone VARCHAR(30) NULL AFTER customer_name,
  ADD COLUMN customer_address TEXT NULL AFTER customer_phone,
  ADD COLUMN payment_method ENUM('cod','online') NULL AFTER customer_address;

CREATE OR REPLACE VIEW vw_shops AS
SELECT
  u.user_id,
  u.username,
  u.email,
  u.role,
  sp.shop_name,
  sp.contact,
  sp.seller_type,
  sp.description,
  sp.address,
  sp.created_at,
  sp.updated_at
FROM users u
JOIN seller_profiles sp ON sp.user_id = u.user_id
WHERE u.role IN ('seller', 'both');
