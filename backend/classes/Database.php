<?php
/**
 * Database Class
 * Handles database connection and common database operations
 * Uses Singleton pattern to ensure only one database connection exists
 */

class Database {
    // Singleton instance
    private static $instance = null;
    
    // Database connection properties
   private $host = 'cloudroute-db.cfy2k6mmsm8a.eu-west-1.rds.amazonaws.com';
private $username = 'admin';
private $password = 'CloudRoute2026!';
private $database = 'cloud_route';
    private $connection;
    
    /**
     * Private constructor to prevent direct instantiation
     * Establishes database connection
     */
    private function __construct() {
        try {
            // Create new MySQLi connection
            $this->connection = new mysqli(
                $this->host,
                $this->username,
                $this->password,
                $this->database
            );
            
            // Check for connection errors
            if ($this->connection->connect_error) {
                throw new Exception("Database connection failed: " . $this->connection->connect_error);
            }
            
            // Set charset to UTF-8
            $this->connection->set_charset('utf8mb4');
            
        } catch (Exception $e) {
            // Log error and throw exception
            error_log($e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get singleton instance of Database
     * @return Database instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get the database connection
     * @return mysqli connection object
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Execute a query and return the result
     * @param string $query SQL query to execute
     * @return mixed Query result or boolean
     */
    public function query($query) {
        $result = $this->connection->query($query);
        
        if ($result === false) {
            error_log("Query error: " . $this->connection->error);
            error_log("Query: " . $query);
        }
        
        return $result;
    }
    
    /**
     * Sanitize input to prevent SQL injection
     * @param string $data Data to sanitize
     * @return string Sanitized data
     */
    public function sanitize($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        $data = $this->connection->real_escape_string($data);
        return $data;
    }
    
    /**
     * Prepare a statement
     * @param string $query SQL query with placeholders
     * @return mysqli_stmt prepared statement
     */
    public function prepare($query) {
        return $this->connection->prepare($query);
    }
    
    /**
     * Get the last inserted ID
     * @return int Last insert ID
     */
    public function getLastInsertId() {
        return $this->connection->insert_id;
    }
    
    /**
     * Get the number of affected rows
     * @return int Number of affected rows
     */
    public function getAffectedRows() {
        return $this->connection->affected_rows;
    }
    
    /**
     * Begin a transaction
     */
    public function beginTransaction() {
        $this->connection->begin_transaction();
    }
    
    /**
     * Commit a transaction
     */
    public function commit() {
        $this->connection->commit();
    }
    
    /**
     * Rollback a transaction
     */
    public function rollback() {
        $this->connection->rollback();
    }
    
    /**
     * Generate a unique ID with prefix
     * @param string $prefix Prefix for the ID
     * @return string Generated unique ID
     */
    public function generateId($prefix = 'ID') {
        return $prefix . time() . rand(1000, 9999);
    }
    
    /**
     * Close the database connection
     */
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
    
    /**
     * Prevent cloning of singleton
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization of singleton
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Destructor - close connection when object is destroyed
     */
    public function __destruct() {
        // Connection will be closed automatically
    }
}
?>

