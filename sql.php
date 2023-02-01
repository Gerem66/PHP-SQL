<?php

    class DataBase
    {
        /** @var mysqli $conn */
        private $conn = null;

        private $db_hostname;
        private $db_database;
        private $db_username;
        private $db_password;

        public function __construct($openConnection = true) {
            if (!file_exists('settings.json')) {
                return;
            }

            $settings = json_decode(file_get_contents('settings.json'));
            $this->db_hostname = $settings->hostname;
            $this->db_database = $settings->database;
            $this->db_username = $settings->username;
            $this->db_password = $settings->password;

            if ($openConnection) {
                $this->OpenConnection();
            }
        }

        public function __destruct() {
            if ($this->conn === null) return;

            $this->conn->close();
            $this->conn = null;
        }

        /**
         * Open a connection to the database.
         * @param bool $exitOnFail If the script should exit if the connection failed.
         * @return bool If the connection was successful.
         * @throws Exception if the connection is already open.
         */
        private function OpenConnection(&$error = null) {
            $this->conn = new mysqli($this->db_hostname, $this->db_username, $this->db_password, $this->db_database);
            if ($this->conn->connect_error) {
                $error = $this->conn->connect_error;
                return false;
            }
            return true;
        }

        function IsSafe($string) {
            return !preg_match('/[^a-zA-Z0-9_]/', $string);
        }

        /**
         * Used to select, insert, update or delete data.
         * @param string $table The table to replace in query.
         * @param string $command The query command to execute.
         * @param string $types The types of the parameters. ('i'(nteger), 'd'(ouble), 's'(tring), 'b'(lob))
         * @param array $variables The variables to bind to the query.
         * @param boolean $execute If the query should be executed or not.
         * @param mixed $query The query to execute.
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
                //print_r($e);
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

        public function GetLastInsertID() {
            return $this->conn->insert_id;
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
