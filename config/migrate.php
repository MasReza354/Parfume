<?php
require_once 'database.php';

// Run database migrations
function runMigrations($conn) {
    echo "Running database migrations...\n";
    
    // Check and add missing columns to orders table
    $columnsToAdd = [
        'shipping_fee' => "ADD COLUMN shipping_fee DECIMAL(10,2) DEFAULT 0 AFTER total_amount",
        'payment_status' => "ADD COLUMN payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending' AFTER payment_method",
        'order_status' => "ADD COLUMN order_status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending' AFTER payment_status",
        'notes' => "ADD COLUMN notes TEXT AFTER shipping_address"
    ];
    
    foreach ($columnsToAdd as $column => $alterQuery) {
        $checkColumn = $conn->query("SHOW COLUMNS FROM orders LIKE '$column'");
        if ($checkColumn->num_rows == 0) {
            $sql = "ALTER TABLE orders " . $alterQuery;
            if ($conn->query($sql) === TRUE) {
                echo "✓ Added column '$column' to orders table\n";
            } else {
                echo "✗ Error adding column '$column': " . $conn->error . "\n";
            }
        } else {
            echo "• Column '$column' already exists in orders table\n";
        }
    }
    
    // Check and add missing columns to order_items table
    $itemColumnsToAdd = [
        'product_name' => "ADD COLUMN product_name VARCHAR(200) AFTER product_id",
        'product_price' => "ADD COLUMN product_price DECIMAL(10,2) AFTER product_name",
        'subtotal' => "ADD COLUMN subtotal DECIMAL(10,2) AFTER price"
    ];
    
    foreach ($itemColumnsToAdd as $column => $alterQuery) {
        $checkColumn = $conn->query("SHOW COLUMNS FROM order_items LIKE '$column'");
        if ($checkColumn->num_rows == 0) {
            $sql = "ALTER TABLE order_items " . $alterQuery;
            if ($conn->query($sql) === TRUE) {
                echo "✓ Added column '$column' to order_items table\n";
            } else {
                echo "✗ Error adding column '$column': " . $conn->error . "\n";
            }
        } else {
            echo "• Column '$column' already exists in order_items table\n";
        }
    }
    
    // Modify product_id to allow NULL in order_items (for non-product items like packing)
    $sql = "ALTER TABLE order_items MODIFY COLUMN product_id INT(11) NULL";
    if ($conn->query($sql) === TRUE) {
        echo "✓ Modified product_id to allow NULL in order_items table\n";
    } else {
        echo "• product_id already allows NULL or error: " . $conn->error . "\n";
    }
    
    // Update existing order_items records to populate missing fields
    $sql = "UPDATE order_items oi 
            LEFT JOIN products p ON oi.product_id = p.id 
            SET oi.product_name = COALESCE(oi.product_name, p.name),
                oi.product_price = COALESCE(oi.product_price, oi.price),
                oi.subtotal = COALESCE(oi.subtotal, oi.quantity * oi.price)
            WHERE oi.product_name IS NULL OR oi.subtotal IS NULL";
    
    if ($conn->query($sql) === TRUE) {
        $affected = $conn->affected_rows;
        if ($affected > 0) {
            echo "✓ Updated $affected existing order_items records\n";
        } else {
            echo "• No order_items records needed updating\n";
        }
    } else {
        echo "✗ Error updating order_items: " . $conn->error . "\n";
    }
    
    echo "\nMigration completed!\n";
}

// Run the migrations
runMigrations($conn);

// Show current table structure
echo "\n=== Current Table Structure ===\n";
$result = $conn->query("DESCRIBE orders");
echo "\nOrders table:\n";
while ($row = $result->fetch_assoc()) {
    echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

$result = $conn->query("DESCRIBE order_items");
echo "\nOrder_items table:\n";
while ($row = $result->fetch_assoc()) {
    echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\nYou can now safely use the checkout and cart features!\n";
?>
