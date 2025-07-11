<?php
$dbfile = __DIR__ . '/database.db';
$pdo = new PDO('sqlite:' . $dbfile);

$pdo->exec("
CREATE TABLE IF NOT EXISTS products (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  productname TEXT NOT NULL,
  categories TEXT,
  barcode TEXT,
  cost REAL,
  price REAL,
  stock INTEGER,
  unit TEXT,
  note TEXT,
  stock_noti INTEGER DEFAULT 5,
  image TEXT
);
CREATE TABLE IF NOT EXISTS customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  adress TEXT,
  category TEXT,
  note TEXT,
  phonenumber TEXT
);
CREATE TABLE IF NOT EXISTS categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  categoryname TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS unit (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  unit TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS supplier (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  adress TEXT,
  phonenumber TEXT,
  note TEXT
);
CREATE TABLE IF NOT EXISTS sales (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sale_date TEXT,
  customer_id INTEGER,
  staff_id INTEGER,
  total REAL,
  note TEXT
);
CREATE TABLE IF NOT EXISTS staff (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  role TEXT,
  phone TEXT,
  email TEXT,
  note TEXT
);
CREATE TABLE IF NOT EXISTS sales_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sale_id INTEGER,
  product_id INTEGER,
  quantity INTEGER,
  price REAL,
  subtotal REAL
);
CREATE TABLE IF NOT EXISTS stock_in_history (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INTEGER,
  qty INTEGER,
  note TEXT,
  created_at TEXT
);
CREATE TABLE IF NOT EXISTS purchase (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INTEGER,
  supplier_id INTEGER,
  qty INTEGER,
  cost REAL,
  date TEXT,
  note TEXT
);
");

// Add supplier column to products if not exists
$cols = $pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_ASSOC);
if (!in_array('supplier', array_column($cols, 'name'))) {
    $pdo->exec("ALTER TABLE products ADD COLUMN supplier INTEGER");
}

// Add image column to products if not exists
$cols = $pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_ASSOC);
if (!in_array('image', array_column($cols, 'name'))) {
    $pdo->exec("ALTER TABLE products ADD COLUMN image TEXT");
}

echo "SQLite database.db & tables created successfully!";
?>