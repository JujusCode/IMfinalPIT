<?php
// Database connection parameters
$host = "localhost";
$port = "5432";
$dbname = "wellmeadows";
$user = "postgres";
$password = "bardinas123";

// Establish connection
try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);

    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set default fetch mode to associative array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // For backward compatibility
    $pdo = $conn;

} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("Database connection error. Please try again later.");
}

/**
 * Function to execute a query
 * @param string $sql SQL query
 * @param array $params Parameters for prepared statement
 * @return array Result set
 */
function executeQuery($sql, $params = [])
{
    global $conn;
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Query Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Function to execute a non-select query (INSERT, UPDATE, DELETE)
 * @param string $sql SQL query
 * @param array $params Parameters for prepared statement
 * @return int Number of affected rows
 */
function executeNonQuery($sql, $params = [])
{
    global $conn;
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Query Error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Function to get a single record
 * @param string $sql SQL query
 * @param array $params Parameters for prepared statement
 * @return array|null Single record or null
 */
function getSingleRecord($sql, $params = [])
{
    global $conn;
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log("Query Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Function to begin a transaction
 * @return bool True on success, false on failure
 */
function beginTransaction()
{
    global $conn;
    return $conn->beginTransaction();
}

/**
 * Function to commit a transaction
 * @return bool True on success, false on failure
 */
function commitTransaction()
{
    global $conn;
    return $conn->commit();
}

/**
 * Function to rollback a transaction
 * @return bool True on success, false on failure
 */
function rollbackTransaction()
{
    global $conn;
    return $conn->rollBack();
}

/**
 * Function to get the last inserted ID
 * @return string Last inserted ID
 */
function getLastInsertId()
{
    global $conn;
    return $conn->lastInsertId();
}