<?php
/**
 * 🌱 ECOTIENDA HN - CONEXIÓN CON LA BASE DE DATOS
 * Ruta: /includes/database.php
 * Descripción: Inicializador de la conexión PDO segura para MySQL utilizando UTF-8 y manejando excepciones de manera elegante.
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $connection = null;

    /**
     * Obtener la única instancia de la conexión PDO
     * @return PDO
     */
    public static function getConnection() {
        if (self::$connection === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];
                
                self::$connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Registrar el error para desarrollo o abortar de forma controlada
                error_log("Error de conexión de base de datos en EcoTienda HN: " . $e->getMessage());
                die('<div style="font-family: Arial, sans-serif; text-align: center; margin-top: 100px; color: #333;">
                        <h2 style="color: #2e7d32;">🌱 EcoTienda HN - Error del Sistema</h2>
                        <p>No se pudo conectar con la base de datos. Por favor intenta más tarde.</p>
                        <p style="font-size: 13px; color: #777;">Detalle técnico: ' . htmlspecialchars($e->getMessage()) . '</p>
                     </div>');
            }
        }
        return self::$connection;
    }
}
?>