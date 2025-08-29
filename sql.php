<?php

    require_once(__DIR__.'/encryption.php');

    class DataBase
    {
        /** @var mysqli $conn */
        private $conn = null;

        /** @var Encryption|null */
        public $encryption = null;

        /** @var mysqli_stmt|null $error_query */
        private $error_query = null;

        private $db_hostname;
        private $db_database;
        private $db_username;
        private $db_password;

        private $default_config_filename = __DIR__ . '/config.json';

        /**
         * DataBase constructor.
         * @param bool $openConnection If the connection should be opened.
         * @param string|null $settingsPath The path to the config file,
         *                        if null the default config file will be used.
         * @throws Exception if the config file is not found or failed to read.
         * @throws Exception if the config file is not valid JSON.
         * @throws Exception if the parsing of the config file failed.
         * @throws Exception if the connection failed.
         */
        public function __construct($openConnection = true, $settingsPath = null) {
            if ($settingsPath === null) {
                $settingsPath = $this->default_config_filename;
            }

            if (!file_exists($settingsPath)) {
                throw(new Exception("{$this->default_config_filename} not found"));
            }

            $settingsContent = file_get_contents($settingsPath);
            if ($settingsContent === false) {
                throw(new Exception("Failed to read {$this->default_config_filename}"));
            }

            $settings = json_decode($settingsContent);
            if ($settings === null) {
                throw(new Exception("Failed to parse {$this->default_config_filename}"));
            }

            $this->db_hostname = $settings->hostname;
            $this->db_database = $settings->database;
            $this->db_username = $settings->username;
            $this->db_password = $settings->password;
            $this->db_port = $settings->port ?? 3306;

            if ($openConnection) {
                // Check and warn to avoid Unix socket issues
                if ($this->db_hostname === 'localhost') {
                    throw new Exception("Configuration error: Use '127.0.0.1' instead of 'localhost' in config.json to avoid Unix socket errors with mysqli.");
                }

                $this->conn = new mysqli(
                    $this->db_hostname,
                    $this->db_username,
                    $this->db_password,
                    $this->db_database,
                    $this->db_port
                );
                if ($this->conn->connect_error) {
                    $error = $this->conn->connect_error;
                    print_r($error);
                    exit;
                    throw(new Exception('Connection failed: ' . $error));
                }
            }

            $this->encryption = new Encryption($settings->{'encrypt-key'}, $settings->{'encrypt-second-key'});
        }

        public function __destruct() {
            if ($this->conn === null) return;

            $this->conn->close();
            $this->conn = null;
        }

        /**
         * Used to get the last error.
         * @return mysqli_stmt|null The last error or null if there is no error.
         */
        public function GetError() {
            if ($this->conn === null || $this->error_query === null) {
                return null;
            }

            $error = $this->error_query;
            $this->error_query = null;
            return $error;
        }

        /**
         * Used to get the last insert ID.
         * @return int The last insert ID.
         */
        public function GetLastInsertID() {
            return $this->conn->insert_id;
        }

        /**
         * Used to check if a string is safe to use in a query.
         * @param string $string The string to check.
         * @return bool True if the string is safe, false otherwise.
         */
        public function IsSafe($string) {
            return !preg_match('/[^a-zA-Z0-9_]/', $string);
        }

        /**
         * Used to select, insert, update or delete data.
         * @param string $table The table to replace in query.
         * @param string $command The query command to execute.
         * @param string $types The types of the parameters. ('i'(nteger), 'd'(ouble), 's'(tring), 'b'(lob))
         * @param array $variables The variables to bind to the query.
         * @param boolean $execute If the query should be executed or not.
         * @param mysqli_stmt|false $query The query to execute.
         * @return array|int|mysqli_stmt|false Array if query type is select, otherwise the number of affected rows or false if the query failed.
         * @throws Exception if the connection is not open.
         */
        public function QueryPrepare($table, $command, $types = '', $variables = array(), $execute = true, $query = false) {
            if ($this->conn === null) {
                throw(new Exception('Connection not opened'));
            }
            if ($table !== null && !$this->IsSafe($table)) {
                throw(new Exception('Invalid table name'));
            }
            if (gettype($variables) !== 'array') {
                throw(new Exception('Invalid variables type (must be an array)'));
            }

            if ($table !== null) {
                $command = str_replace('TABLE', "`$table`", $command);
            }

            if ($query === false) {
                $query = $this->conn->prepare($command);
            }
            if ($query === false) return false;

            if (count($variables)) {
                $bind = $query->bind_param($types, ...$variables);
                if ($bind === false) return false;
            }

            try {
                $result = $query->execute();
                if ($result === false) return false;
            } catch (Exception $e) {
                $this->error_query = $query;
                return false;
            }

            if (!$execute) {
                return $query;
            }

            $output = $query->affected_rows;
            if (str_starts_with($command, 'SELECT')) {
                $output = $query->get_result()->fetch_all(MYSQLI_ASSOC);
            }

            $query->close();
            return $output;
        }

        /**
         * Get the tables in the database.
         * @return array|false Array of tables or false if the query failed.
         */
        public function GetTables() {
            $tables = null;
            $query = $this->conn->query('SHOW TABLES');
            if ($query === false) return false;

            $tables = array();
            while ($row = $query->fetch_assoc()) {
                array_push($tables, $row);
            }
            if ($tables === null) return false;

            $tableMap = fn($table) => $table["Tables_in_{$this->db_database}"];
            return array_map($tableMap, $tables);
        }

        /**
         * Return IP address of the client or "UNKNOWN" if failed
         * @link https://stackoverflow.com/questions/3003145/how-to-get-the-client-ip-address-in-php
         * @return string|false
         */
        function GetClientIP() {
            $keys = array(
                'REMOTE_ADDR',
                'HTTP_FORWARDED',
                'HTTP_CLIENT_IP',
                'HTTP_FORWARDED_FOR',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED'
            );
            foreach ($keys as $k) {
                if (!empty($_SERVER[$k])) {
                    return $_SERVER[$k];
                }
            }
            return false;
        }
    }

?>
