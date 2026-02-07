<?php
require_once 'config.php';

class M35Database {
    private static $coreConnection = null;
    private static $tenantConnection = null;

    // Connessione al Database CORE (Anagrafiche Generali)
    public static function getCore() {
        if (self::$coreConnection === null) {
            try {
                $dsn = "mysql:host=".DB_CORE_HOST.";dbname=".DB_CORE_NAME.";charset=utf8mb4";
                self::$coreConnection = new PDO($dsn, DB_CORE_USER, DB_CORE_PASS);
                self::$coreConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die("ERRORE CRITICO M3.5 CORE: " . $e->getMessage());
            }
        }
        return self::$coreConnection;
    }

    // Connessione al Database TENANT (Dati Cliente Specifico)
    public static function getTenant() {
        if (self::$tenantConnection === null) {
            try {
                // Per ora forziamo la connessione a Beauty. 
                // In futuro qui passeremo l'ID del cliente.
                $dsn = "mysql:host=".DB_CORE_HOST.";dbname=".DB_BEAUTY_NAME.";charset=utf8mb4";
                self::$tenantConnection = new PDO($dsn, DB_BEAUTY_USER, DB_BEAUTY_PASS);
                self::$tenantConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                die("ERRORE CRITICO TENANT DB: " . $e->getMessage());
            }
        }
        return self::$tenantConnection;
    }
}
?>