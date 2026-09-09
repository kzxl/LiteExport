# LiteExport 📊

Ultra-fast, memory-efficient streaming Excel (.xlsx) and CSV exporter in pure PHP 8.2+ with zero external dependencies (< 16MB RAM for 100K+ rows).

[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![License: Apache-2.0](https://img.shields.io/badge/License-Apache_2.0-green.svg)](LICENSE)
[![Zero External Dependencies](https://img.shields.io/badge/Dependencies-0%20(Pure%20PHP)-brightgreen.svg)]()
[![Tests](https://img.shields.io/badge/Tests-4%2F4%20Pass%20(100%25)-brightgreen.svg)]()

---

## ⚡ Why LiteExport?

Standard PHP spreadsheet libraries (such as `phpoffice/phpspreadsheet`) allocate heavyweight in-memory cell models, consuming **80MB to 300MB+ of RAM** for large exports and frequently causing Out-Of-Memory (OOM) crashes in Docker containers and web workers.

**LiteExport** bypasses in-memory DOM allocations completely:
- Streams row-by-row directly into compressed OpenXML parts on disk using PHP Generators.
- Includes a pure PHP OpenXML Zip packager (`SimpleZip`) utilizing native `zlib`—**runs without even requiring `ext-zip`**.
- Memory usage stays strictly under **16MB** regardless of dataset size (1,000 or 500,000 rows).
- Integrates seamlessly with `LiteORM::cursor()` for end-to-end $O(1)$ memory database exports.

---

## 📦 Installation

```bash
composer require kzxl/lite-export
```

---

## 🚀 Usage Guide

### 1. High-Performance Excel (.xlsx) Export

```php
use LiteExport\Exporter;

// Export from array
$data = [
    ['id' => 1, 'name' => 'Nguyễn Văn A', 'price' => 150.00],
    ['id' => 2, 'name' => 'Trần Thị B', 'price' => 320.50],
];

Exporter::xlsx($data, '/path/to/report.xlsx', sheetName: 'Customers');

// Export from LiteORM database cursor with O(1) memory!
$usersCursor = $em->query(User::class)->cursor(); // Yields one row at a time
Exporter::xlsx($usersCursor, '/path/to/large_users_export.xlsx');
```

---

### 2. High-Performance CSV Export (with UTF-8 BOM for Excel)

```php
use LiteExport\Exporter;

// Auto-prepends UTF-8 BOM so Microsoft Excel opens Vietnamese / Unicode without errors
Exporter::csv($usersCursor, '/path/to/export.csv');

// Or export directly to string
$csvString = Exporter::csv($data);
```

---

## 🧪 Testing

```bash
composer test
# or
./vendor/bin/phpunit
```

---

## 📄 License

Released under the **Apache-2.0 License**.  
Architected by **Phong Võ** (`kzxl`).
