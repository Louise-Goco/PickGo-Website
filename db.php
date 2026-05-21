<?php

$host = 'localhost';
$user = 'root';      
$pass = 'goco';          
$dbname = 'pickaroo_db';

try {
    
    $options = [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];
    $pdo = new PDO("mysql:host=$host", $user, $pass, $options);
    
    // Create the database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    
    // Connect to the specific database
    $pdo->exec("USE `$dbname`");
    
    // Skip all table creation and ALTER queries if schema is already built (massive speedup)
    $schemaBuilt = false;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'users'");
        $schemaBuilt = ($check->rowCount() > 0);
    } catch (Exception $e) {}

    // Ensure default promo codes exist
    try {
        $pdo->exec("INSERT IGNORE INTO promo_codes (Code, Discount_Type, Discount_Value, Expiry_Date, Usage_Limit, Is_Active) VALUES ('WELCOME50', 'fixed', 50.00, '2027-12-31', 1000, 1)");
        $pdo->exec("INSERT IGNORE INTO promo_codes (Code, Discount_Type, Discount_Value, Expiry_Date, Usage_Limit, Is_Active) VALUES ('PICKGO20', 'percentage', 20.00, '2027-12-31', 500, 1)");
    } catch (Exception $e) {}

    if (!$schemaBuilt) {
        // Create the users table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        display_name VARCHAR(100) DEFAULT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        phone_number VARCHAR(20) NOT NULL,
        password VARCHAR(255) NOT NULL,
        profile_photo VARCHAR(255) DEFAULT NULL,
        otp_code VARCHAR(10) DEFAULT NULL,
        is_verified TINYINT(1) DEFAULT 0,
        user_type ENUM('customer', 'admin') DEFAULT 'customer',
        account_status ENUM('active', 'suspended', 'deactivated') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);

    // Patch for existing table to safely add columns without dropping the data
    try { $pdo->exec("ALTER TABLE users ADD COLUMN display_name VARCHAR(100) DEFAULT NULL"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN otp_code VARCHAR(10) DEFAULT NULL"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN user_type ENUM('customer', 'admin') DEFAULT 'customer'"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN account_status ENUM('active', 'suspended', 'deactivated') DEFAULT 'active'"); } catch(PDOException $e) {}

    // Create addresses table for users
    $sqlAddresses = "CREATE TABLE IF NOT EXISTS addresses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        label VARCHAR(50) NOT NULL, 
        address_line_1 VARCHAR(255) NOT NULL,
        city VARCHAR(100) NOT NULL,
        is_default TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sqlAddresses);

    // Create merchants table
    $sqlMerchants = "CREATE TABLE IF NOT EXISTS merchants (
        Merch_Id INT AUTO_INCREMENT PRIMARY KEY,
        Merch_Name VARCHAR(255) NOT NULL,
        Merch_Type VARCHAR(100) NOT NULL,
        Merch_Address VARCHAR(255) NOT NULL,
        Merch_UnitFloor VARCHAR(50),
        Merch_Building VARCHAR(255),
        Merch_StreetNo VARCHAR(50),
        Merch_StreetName VARCHAR(255),
        Merch_Barangay VARCHAR(255),
        Merch_City VARCHAR(255),
        Merch_Province VARCHAR(255),
        Merch_ZIP VARCHAR(20),
        Merch_Landmark VARCHAR(255),
        Merch_Description TEXT,
        Merch_ContactNumber VARCHAR(20) NOT NULL,
        Merch_Email VARCHAR(255) NOT NULL UNIQUE,
        Merch_OpeningTime TIME,
        Merch_ClosingTime TIME,
        Merch_Logo VARCHAR(255),
        Merch_Banner VARCHAR(255),
        Merch_DeliveryRange INT DEFAULT 5,
        Merch_GovID VARCHAR(255),
        Merch_BIRCert VARCHAR(255),
        Merch_Status ENUM('pending', 'active', 'suspended', 'closed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sqlMerchants);

    // Patch for merchants table
    try { $pdo->exec("ALTER TABLE merchants ADD COLUMN Merch_UnitFloor VARCHAR(50)"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE merchants ADD COLUMN Merch_Building VARCHAR(255)"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE merchants ADD COLUMN Merch_StreetNo VARCHAR(50)"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE merchants ADD COLUMN Merch_StreetName VARCHAR(255)"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE merchants ADD COLUMN Merch_Barangay VARCHAR(255)"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE merchants ADD COLUMN Merch_City VARCHAR(255)"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE merchants ADD COLUMN Merch_Province VARCHAR(255)"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE merchants ADD COLUMN Merch_ZIP VARCHAR(20)"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE merchants ADD COLUMN Merch_Landmark VARCHAR(255)"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE merchants ADD COLUMN Merch_Logo VARCHAR(255)"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE merchants ADD COLUMN Merch_Banner VARCHAR(255)"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE merchants ADD COLUMN Merch_DeliveryRange INT DEFAULT 5"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE merchants ADD COLUMN Merch_GovID VARCHAR(255)"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE merchants ADD COLUMN Merch_BIRCert VARCHAR(255)"); } catch(PDOException $e) {}

    // Create sellers table
    $sqlSellers = "CREATE TABLE IF NOT EXISTS sellers (
        Seller_Id INT AUTO_INCREMENT PRIMARY KEY,
        Sellr_Fname VARCHAR(100) NOT NULL,
        Sellr_Lname VARCHAR(100) NOT NULL,
        Sellr_Email VARCHAR(255) NOT NULL UNIQUE,
        Sellr_Password VARCHAR(255) NOT NULL,
        Sellr_PhoneNumber VARCHAR(20) NOT NULL,
        Sellr_Bio TEXT,
        Sellr_Status ENUM('pending', 'active', 'suspended', 'rejected') DEFAULT 'pending',
        Sellr_Rating DECIMAL(3,1) DEFAULT 0.0,
        Sellr_DateCreated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        Merch_Id INT,
        FOREIGN KEY (Merch_Id) REFERENCES merchants(Merch_Id) ON DELETE SET NULL
    )";
    $pdo->exec($sqlSellers);

    // Create riders table
    $sqlRiders = "CREATE TABLE IF NOT EXISTS riders (
        Rider_Id INT AUTO_INCREMENT PRIMARY KEY,
        Rider_Fname VARCHAR(100) NOT NULL,
        Rider_Lname VARCHAR(100) NOT NULL,
        Rider_Email VARCHAR(255) NOT NULL UNIQUE,
        Rider_Password VARCHAR(255) NOT NULL,
        Rider_Phone VARCHAR(20) NOT NULL,
        Rider_VehicleType VARCHAR(50) NOT NULL,
        Rider_PlateNumber VARCHAR(20) NOT NULL,
        Rider_LicenseNumber VARCHAR(50) NOT NULL,
        Rider_Status ENUM('pending', 'active', 'suspended', 'offline', 'rejected') DEFAULT 'pending',
        Rider_Verified TINYINT(1) DEFAULT 0,
        Rider_Rating DECIMAL(3,1) DEFAULT 0.0,
        Rider_TotalDeliveries INT DEFAULT 0,
        Rider_SuccessRate DECIMAL(5,2) DEFAULT 100.00,
        Rider_Photo VARCHAR(255) DEFAULT NULL,
        Rider_BankName VARCHAR(100) DEFAULT NULL,
        Rider_BankAccNo VARCHAR(50) DEFAULT NULL,
        Rider_BankAccName VARCHAR(100) DEFAULT NULL,
        Rider_LicensePhoto VARCHAR(255) DEFAULT NULL,
        Rider_NBI VARCHAR(255) DEFAULT NULL,
        Rider_OR VARCHAR(255) DEFAULT NULL,
        Rider_CR VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sqlRiders);

    // Patch for riders table
    try { $pdo->exec("ALTER TABLE riders ADD COLUMN Rider_Photo VARCHAR(255) DEFAULT NULL"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE riders ADD COLUMN Rider_BankName VARCHAR(100) DEFAULT NULL"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE riders ADD COLUMN Rider_LicensePhoto VARCHAR(255) DEFAULT NULL"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE riders ADD COLUMN Rider_NBI VARCHAR(255) DEFAULT NULL"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE riders ADD COLUMN Rider_OR VARCHAR(255) DEFAULT NULL"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE riders ADD COLUMN Rider_CR VARCHAR(255) DEFAULT NULL"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE riders ADD COLUMN Rider_BankAccNo VARCHAR(50) DEFAULT NULL"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE riders ADD COLUMN Rider_BankAccName VARCHAR(100) DEFAULT NULL"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE riders ALTER COLUMN Rider_Rating SET DEFAULT 0.0"); } catch(PDOException $e) {}

    // Create orders table
    $sqlOrders = "CREATE TABLE IF NOT EXISTS orders (
        Order_Id INT AUTO_INCREMENT PRIMARY KEY,
        Customer_Id INT NOT NULL,
        Seller_Id INT NOT NULL,
        Rider_Id INT,
        Batch_Id VARCHAR(50) DEFAULT NULL,
        Order_Total DECIMAL(10,2) NOT NULL,
        Order_Status ENUM('pending', 'preparing', 'ready_for_pickup', 'on_the_way', 'delivered', 'cancelled') DEFAULT 'pending',
        Delivery_Address TEXT NOT NULL,
        Payment_Method VARCHAR(50) NOT NULL,
        Order_ProofPhoto VARCHAR(255) DEFAULT NULL,
        Rider_Earnings DECIMAL(10,2) DEFAULT 0.00,
        Order_Date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (Customer_Id) REFERENCES users(id),
        FOREIGN KEY (Seller_Id) REFERENCES sellers(Seller_Id),
        FOREIGN KEY (Rider_Id) REFERENCES riders(Rider_Id)
    )";
    $pdo->exec($sqlOrders);

    // Patch for orders table
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN Order_ProofPhoto VARCHAR(255) DEFAULT NULL"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN Rider_Earnings DECIMAL(10,2) DEFAULT 0.00"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN Batch_Id VARCHAR(50) DEFAULT NULL"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE orders MODIFY COLUMN Order_Status ENUM('pending', 'preparing', 'ready_for_pickup', 'on_the_way', 'delivered', 'cancelled') DEFAULT 'pending'"); } catch(PDOException $e) {}

    // Create order_items table
    $sqlOrderItems = "CREATE TABLE IF NOT EXISTS order_items (
        Item_Id INT AUTO_INCREMENT PRIMARY KEY,
        Order_Id INT NOT NULL,
        Food_Name VARCHAR(255) NOT NULL,
        Quantity INT NOT NULL,
        Price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (Order_Id) REFERENCES orders(Order_Id) ON DELETE CASCADE
    )";
    $pdo->exec($sqlOrderItems);

    // Create items table
    $sqlItems = "CREATE TABLE IF NOT EXISTS items (
        Item_Id INT AUTO_INCREMENT PRIMARY KEY,
        Seller_Id INT NOT NULL,
        Item_Name VARCHAR(255) NOT NULL,
        Item_Description TEXT,
        Item_Price DECIMAL(10,2) NOT NULL,
        Item_Category INT,
        Item_Image VARCHAR(255),
        Item_Status ENUM('pending', 'available', 'rejected', 'out_of_stock', 'discontinued') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (Seller_Id) REFERENCES sellers(Seller_Id) ON DELETE CASCADE,
        FOREIGN KEY (Item_Category) REFERENCES categories(Categ_Id) ON DELETE SET NULL
    )";
    $pdo->exec($sqlItems);

    // Create category table
    $sqlCategories = "CREATE TABLE IF NOT EXISTS categories (
        Categ_Id INT AUTO_INCREMENT PRIMARY KEY,
        Categ_Name VARCHAR(100) NOT NULL UNIQUE,
        Categ_Description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sqlCategories);

    // Create payouts table
    $sqlPayouts = "CREATE TABLE IF NOT EXISTS payouts (
        Payout_Id INT AUTO_INCREMENT PRIMARY KEY,
        User_Type ENUM('seller', 'rider') NOT NULL,
        User_Id INT NOT NULL,
        Amount DECIMAL(10,2) NOT NULL,
        Bank_Name VARCHAR(100) NOT NULL,
        Account_Number VARCHAR(50) NOT NULL,
        Account_Name VARCHAR(100) NOT NULL,
        Payout_Status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending',
        Request_Date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        Processed_Date TIMESTAMP NULL
    )";
    $pdo->exec($sqlPayouts);

    // Create settings table
    $sqlSettings = "CREATE TABLE IF NOT EXISTS settings (
        Setting_Key VARCHAR(100) PRIMARY KEY,
        Setting_Value VARCHAR(255) NOT NULL,
        Updated_At TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sqlSettings);

    // Initialize default settings
    $pdo->exec("INSERT IGNORE INTO settings (Setting_Key, Setting_Value) VALUES ('delivery_fee', '49.00')");
    $pdo->exec("INSERT IGNORE INTO settings (Setting_Key, Setting_Value) VALUES ('service_fee', '15.00')");
    $pdo->exec("INSERT IGNORE INTO settings (Setting_Key, Setting_Value) VALUES ('tax_rate', '12.00')");
    
    // Create promo_codes table
    $sqlPromos = "CREATE TABLE IF NOT EXISTS promo_codes (
        Promo_Id INT AUTO_INCREMENT PRIMARY KEY,
        Code VARCHAR(50) NOT NULL UNIQUE,
        Discount_Type ENUM('percentage', 'fixed') NOT NULL,
        Discount_Value DECIMAL(10,2) NOT NULL,
        Expiry_Date DATE NOT NULL,
        Usage_Limit INT DEFAULT 0,
        Current_Usage INT DEFAULT 0,
        Is_Active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sqlPromos);
    
    // Insert default promo codes for testing/use
    $pdo->exec("INSERT IGNORE INTO promo_codes (Code, Discount_Type, Discount_Value, Expiry_Date, Usage_Limit, Is_Active) VALUES ('WELCOME50', 'fixed', 50.00, '2027-12-31', 1000, 1)");
    $pdo->exec("INSERT IGNORE INTO promo_codes (Code, Discount_Type, Discount_Value, Expiry_Date, Usage_Limit, Is_Active) VALUES ('PICKGO20', 'percentage', 20.00, '2027-12-31', 500, 1)");

    // Create reviews table
    $sqlReviews = "CREATE TABLE IF NOT EXISTS reviews (
        Review_Id INT AUTO_INCREMENT PRIMARY KEY,
        Order_Id INT NOT NULL,
        Customer_Id INT NOT NULL,
        Seller_Id INT NOT NULL,
        Item_Id INT NULL, -- NULL if it's a store-wide review
        Rating TINYINT NOT NULL CHECK (Rating BETWEEN 1 AND 5),
        Comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (Order_Id) REFERENCES orders(Order_Id) ON DELETE CASCADE,
        FOREIGN KEY (Customer_Id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (Seller_Id) REFERENCES sellers(Seller_Id) ON DELETE CASCADE,
        FOREIGN KEY (Item_Id) REFERENCES items(Item_Id) ON DELETE SET NULL
    )";
    $pdo->exec($sqlReviews);

    // Patch for reviews table to support rider reviews
    try { $pdo->exec("ALTER TABLE reviews MODIFY COLUMN Seller_Id INT NULL"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE reviews ADD COLUMN Rider_Id INT NULL"); } catch(PDOException $e) {}
    try { $pdo->exec("ALTER TABLE reviews ADD FOREIGN KEY (Rider_Id) REFERENCES riders(Rider_Id) ON DELETE CASCADE"); } catch(PDOException $e) {}

    // Initialize Payment Method settings
    $pdo->exec("INSERT IGNORE INTO settings (Setting_Key, Setting_Value) VALUES ('payment_cod_enabled', '1')");
    $pdo->exec("INSERT IGNORE INTO settings (Setting_Key, Setting_Value) VALUES ('payment_gcash_enabled', '1')");
    $pdo->exec("INSERT IGNORE INTO settings (Setting_Key, Setting_Value) VALUES ('payment_card_enabled', '1')");
    
    // Create cart table for persistent cart storage
    $sqlCart = "CREATE TABLE IF NOT EXISTS cart (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        item_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (item_id) REFERENCES items(Item_Id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_item (user_id, item_id)
    )";
    $pdo->exec($sqlCart);
    }
} catch(PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
