<?php

// --- Database Configuration ---
// IMPORTANT: Replace with your actual database credentials
$dbHost = 'localhost';
$dbUser = 'root';      // Default XAMPP/MAMP username
$dbPass = '';          // Default XAMPP/MAMP password (often empty)
$dbName = 'placement_dashboard'; // The database name you created

// --- Helper Functions ---

/**
 * Establishes a database connection.
 * @return mysqli|false Connection object or false on failure.
 */
function getDbConnection() {
    global $dbHost, $dbUser, $dbPass, $dbName;

    // Check if .env file exists for credentials
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $env = parse_ini_file($envFile);
        $dbHost = $env['DB_HOST'] ?? $dbHost;
        $dbUser = $env['DB_USER'] ?? $dbUser;
        $dbPass = $env['DB_PASS'] ?? $dbPass;
        $dbName = $env['DB_NAME'] ?? $dbName;
    }

    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        error_log("Database Connection Failed: " . $conn->connect_error);
        // Do not output connection error directly to client for security reasons in production
        return false;
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * Builds the WHERE clause for SQL query based on filters.
 * Uses prepared statements to prevent SQL injection.
 * @param mysqli $conn The database connection object.
 * @param array $params The GET request parameters.
 * @param array &$bindParams Array to store parameters for binding.
 * @param string &$bindTypes String to store types for binding.
 * @return string The WHERE clause string (e.g., " WHERE `col` LIKE ? AND `col2` = ?").
 */
function buildWhereClauseSecure($conn, $params, &$bindParams, &$bindTypes) {
    $whereConditions = [];
    $filterMappings = [
        // paramKey from JS => dbColumn
        'company_name' => 'company',
        'name' => 'name',
        'website' => 'website',
        'email' => 'email',
        'mobile' => 'mobile',
        'address' => 'address'
    ];

    foreach ($filterMappings as $paramKey => $dbColumn) {
        if (!empty($params[$paramKey])) {
            $value = $params[$paramKey]; // Raw value, will be bound
            $type = isset($params[$paramKey . '_type']) ? $params[$paramKey . '_type'] : 'contains';

            switch ($type) {
                case 'startsWith':
                    $whereConditions[] = "`" . $dbColumn . "` LIKE ?";
                    $bindParams[] = $value . "%";
                    $bindTypes .= "s";
                    break;
                case 'includes':
                case 'contains':
                    $whereConditions[] = "`" . $dbColumn . "` LIKE ?";
                    $bindParams[] = "%" . $value . "%";
                    $bindTypes .= "s";
                    break;
                case 'excludes':
                    $whereConditions[] = "`" . $dbColumn . "` NOT LIKE ?";
                    $bindParams[] = "%" . $value . "%"; // For NOT LIKE, %value% is common
                    $bindTypes .= "s";
                    break;
            }
        }
    }

    // City filter (direct match)
    if (!empty($params['city'])) {
        $whereConditions[] = "`city` = ?";
        $bindParams[] = $params['city'];
        $bindTypes .= "s";
    }

    return count($whereConditions) > 0 ? " WHERE " . implode(" AND ", $whereConditions) : "";
}


/**
 * Fetches distinct city names from the database.
 * @param mysqli $conn Database connection object.
 */
function getCities($conn) {
    $sql = "SELECT DISTINCT `city` FROM `data` WHERE `city` IS NOT NULL AND `city` != '' ORDER BY `city` ASC";
    $result = $conn->query($sql); // Simple query, no user input, direct query is fine
    $cities = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $cities[] = $row['city'];
        }
        $result->free();
        echo json_encode(['cities' => $cities]);
    } else {
        // Log the detailed error, provide a generic one to the client
        error_log("Failed to fetch cities: " . $conn->error);
        echo json_encode(['error' => 'Failed to retrieve city list.']);
    }
}

/**
 * Fetches data based on filters using prepared statements.
 * @param mysqli $conn Database connection object.
 * @param array $params GET request parameters.
 */
function getData($conn, $params) {
    $bindParams = [];
    $bindTypes = "";
    $whereClause = buildWhereClauseSecure($conn, $params, $bindParams, $bindTypes);
    
    $sql = "SELECT `company`, `name`, `mobile`, `email`, `city`, `address`, `pincode`, `website`, `category`, `State` FROM `data`" . $whereClause . " ORDER BY `company` ASC, `name` ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for getData: (" . $conn->errno . ") " . $conn->error . " SQL: " . $sql);
        echo json_encode(['error' => 'An error occurred while preparing data.']);
        return;
    }

    if (!empty($bindTypes) && !empty($bindParams)) {
        // The splat operator (...) unpacks the $bindParams array into individual arguments
        if (!$stmt->bind_param($bindTypes, ...$bindParams)) {
            error_log("Binding parameters failed for getData: (" . $stmt->errno . ") " . $stmt->error);
            echo json_encode(['error' => 'An error occurred while binding parameters.']);
            $stmt->close();
            return;
        }
    }
    
    if (!$stmt->execute()) {
        error_log("Execute failed for getData: (" . $stmt->errno . ") " . $stmt->error);
        echo json_encode(['error' => 'An error occurred while fetching data.']);
        $stmt->close();
        return;
    }

    $result = $stmt->get_result();
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $result->free();
    } else {
        error_log("Getting result set failed for getData: (" . $stmt->errno . ") " . $stmt->error);
        echo json_encode(['error' => 'Failed to retrieve results.']);
        $stmt->close();
        return;
    }
    
    $stmt->close();
    echo json_encode(['data' => $data]);
}


/**
 * Exports data to CSV format using prepared statements.
 * @param mysqli $conn Database connection object.
 * @param array $params GET request parameters.
 */
function exportCsv($conn, $params) {
    $bindParams = [];
    $bindTypes = "";
    $whereClause = buildWhereClauseSecure($conn, $params, $bindParams, $bindTypes);
    
    // Select all columns as per "Export Columns : All columns select option"
    $sql = "SELECT `company`, `name`, `mobile`, `email`, `address`, `pincode`, `city`, `website`, `category`, `State` FROM `data`" . $whereClause . " ORDER BY `company` ASC, `name` ASC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        header('Content-Type: text/plain');
        error_log("Prepare failed for exportCsv: (" . $conn->errno . ") " . $conn->error . " SQL: " . $sql);
        echo "Error preparing CSV data.";
        return;
    }

    if (!empty($bindTypes) && !empty($bindParams)) {
        if (!$stmt->bind_param($bindTypes, ...$bindParams)) {
            header('Content-Type: text/plain');
            error_log("Binding parameters failed for exportCsv: (" . $stmt->errno . ") " . $stmt->error);
            echo "Error binding parameters for CSV.";
            $stmt->close();
            return;
        }
    }

    if (!$stmt->execute()) {
        header('Content-Type: text/plain');
        error_log("Execute failed for exportCsv: (" . $stmt->errno . ") " . $stmt->error);
        echo "Error executing query for CSV.";
        $stmt->close();
        return;
    }

    $result = $stmt->get_result();

    if (!$result) {
        header('Content-Type: text/plain');
        error_log("Getting result set failed for exportCsv: (" . $stmt->errno . ") " . $stmt->error);
        echo "Error retrieving data for CSV.";
        $stmt->close();
        return;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="placement_data_' . date('Y-m-d_H-i-s') . '.csv"');

    $output = fopen('php://output', 'w');

    // Add headers
    fputcsv($output, ['Company', 'Name', 'Mobile', 'Email', 'Address', 'Pincode', 'City', 'Website', 'Category', 'State']);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }

    fclose($output);
    $result->free();
    $stmt->close();
}


// --- Main Controller ---
$action = isset($_GET['action']) ? $_GET['action'] : '';

if (empty($action)) {
    if (isset($_POST['action'])) { // Also check POST for action, though GET is typical for this setup
        $action = $_POST['action'];
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No action specified.']);
        exit;
    }
}


$conn = getDbConnection();
if (!$conn) {
    // For AJAX requests, return JSON error.
    // For CSV, a plain text error is more appropriate if headers haven't been sent.
    if ($action !== 'exportCsv') {
         header('Content-Type: application/json');
    } else {
         header('Content-Type: text/plain');
    }
    // Provide a generic error to the client, log the specific one.
    echo json_encode(['error' => 'Database connection error. Please contact an administrator.']);
    exit;
}

// Set default content type for JSON responses, can be overridden by exportCsv
if ($action !== 'exportCsv') {
    header('Content-Type: application/json');
}


switch ($action) {
    case 'getCities':
        getCities($conn);
        break;
    case 'getData':
        // Use $_GET for getData as per JavaScript fetch call
        getData($conn, $_GET);
        break;
    case 'exportCsv':
        // Use $_GET for exportCsv as per JavaScript window.location.href
        exportCsv($conn, $_GET);
        break;
    default:
        if ($action !== 'exportCsv') { // Avoid sending JSON if it was an export attempt with bad action
            echo json_encode(['error' => 'Invalid action specified.']);
        } else {
            header('Content-Type: text/plain');
            echo 'Invalid action for export.';
        }
}

if ($conn) {
    $conn->close();
}

?>
