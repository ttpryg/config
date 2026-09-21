# Config Manager Library

A lightweight, standalone PHP configuration management library featuring dot-notation syntax, ArrayAccess support, multi-file directory loading, and database persistence (PDO / Custom Drivers).

## Features
- **Dot notation support**: Access deeply nested values using `$config->get('database.connections.mysql.host')`.
- **ArrayAccess Interface**: Treat configuration object like an array `$config['app.name']`.
- **Directory Auto-loader**: Automatically load all `.php` configuration files in a directory into key-partitioned arrays.
- **Database Storage & Persistence**: Load and save configuration directly to a database table via PDO driver (`PdoDatabaseDriver`) or custom drivers.
- **Zero External Runtime Dependencies**: Self-contained with zero external dependencies, easily portable across projects or published as a standalone composer package.
- **Optional Value Encryption**: Persist config values to the database encrypted with OpenSSL AES-256-GCM (`ConfigEncryptor`), kept plain in memory.

## Package Structure (Composerable)

```text
ttpryg/config/
├── composer.json
├── README.md
├── src/
│   ├── ConfigInterface.php
│   ├── ConfigRepository.php
│   ├── ConfigManager.php
│   ├── Encryption/
│   │   └── ConfigEncryptor.php
│   └── Drivers/
│       ├── DatabaseDriverInterface.php
│       └── PdoDatabaseDriver.php
└── tests/
    ├── ConfigRepositoryTest.php
    └── ConfigEncryptorTest.php
```

## How to Reuse in Another Repository

1. **Option A: Copy Folder Directly**
   Copy the `ttpryg/config/` folder into your new project and add the PSR-4 namespace to your project's `composer.json`:
   ```json
   "autoload": {
       "psr-4": {
           "Ttpryg\\Config\\": "ttpryg/config/src/"
       }
   }
   ```

2. **Option B: Path Repository (Local Composer Package)**
   Add as a local path repository in another project's `composer.json`:
   ```json
   "repositories": [
        {
            "type": "path",
            "url": "./ttpryg/config"
        }
    ],
   "require": {
       "ttpryg/config": "*"
   }
   ```

3. **Option C: Publish to Git / Packagist**
   Push the `ttpryg/config` directory to its own GitHub repository (e.g. `github.com/your-username/config`) and require it via standard composer:
   ```bash
   composer require ttpryg/config
   ```

## Quick Start Example

```php
use Ttpryg\Config\ConfigManager;
use Ttpryg\Config\ConfigRepository;
use Ttpryg\Config\Drivers\PdoDatabaseDriver;

// Method 1: Create manually
$config = new ConfigRepository([
    'app' => [
        'name' => 'My App',
        'env' => 'production',
    ],
]);

// Access via dot-notation
$appName = $config->get('app.name'); // "My App"
$debug = $config->get('app.debug', false); // false (default)

// Set nested values
$config->set('database.mysql.host', '127.0.0.1');

// ArrayAccess syntax
$config['jwt.secret'] = 'secret-key';
$secret = $config['jwt.secret'];

// Method 2: Load from directory containing config PHP files
$config = ConfigManager::createFromDirectory(__DIR__ . '/config');

// Method 3: Database Storage (PDO Driver)
$pdo = new PDO('mysql:host=localhost;dbname=slim_db', 'root', 'password');
$dbDriver = new PdoDatabaseDriver($pdo, 'configs');

// Save config to database
$config->saveToDatabase($dbDriver);

// Load config from database
$dbConfig = ConfigManager::createFromDatabase($dbDriver);
$siteName = $dbConfig->get('site.name');
```

## Optional Value Encryption

Encrypt config values at rest in the database using OpenSSL AES-256-GCM. Values are kept plain in memory and only encrypted when persisted via `saveToDatabase()`.

Requires the PHP `openssl` extension (enabled by default in most PHP installations).

```php
use Ttpryg\Config\ConfigManager;
use Ttpryg\Config\ConfigRepository;
use Ttpryg\Config\Encryption\ConfigEncryptor;

$encryptor = new ConfigEncryptor('your-app-secret-key');

// Save with encrypted values
$config = new ConfigRepository([
    'stripe' => ['secret' => 'sk_live_...'],
], $encryptor);
$config->saveToDatabase($dbDriver);

// Load and decrypt with the same key
$dbConfig = ConfigManager::createFromDatabase($dbDriver, $encryptor);
$stripeSecret = $dbConfig->get('stripe.secret'); // "sk_live_..."
```

- Encrypted rows are stored with the `enc:v1:` marker prefix so they are auto-detected and decrypted on load.
- Existing plain (legacy) rows remain readable — only values with the marker are decrypted.
- Loading without an encryptor yields the raw encrypted strings untouched.
