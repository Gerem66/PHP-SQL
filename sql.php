<?php

    require('./credentials.php');

    class DataBase
    {
        /**
         * @var mysqli $conn
         */
        private $conn;
        private $db_hostname;
        private $db_name;
        private $db_username;
        private $db_password;

        public function __construct($init_conn = true) {
            list(
                $this->db_hostname,
                $this->db_name,
                $this->db_username,
                $this->db_password
            ) = GetCredentials();
            if ($init_conn) {
                $this->OpenConnection();
            }
        }

        public function __destruct() {
            $this->conn->close();
        }

        private function OpenConnection() {
            $this->conn = new mysqli($this->db_hostname, $this->db_username, $this->db_password, $this->db_name);
            if ($this->conn->connect_error) {
                die('Connection failed: ' . $this->conn->connect_error);
            }
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

        public function GetTables() {
            $tables = null;
            $query = $this->conn->query('SHOW TABLES');
            if ($query === false) return false;

            $tables = array();
            while ($row = $query->fetch_assoc()) {
                array_push($tables, $row);
            }
            if ($tables === null) return null;

            $tableMap = fn($table) => $table["Tables_in_{$this->db_name}"];
            return array_map($tableMap, $tables);
        }

        public function GetLastInsertID() {
            return $this->conn->insert_id;
        }

        /**
         * @param int $accountID 0 if not connected
         * @param int $deviceID
         * @param string $type
         * @param string $data
         * @return bool Success of operation
         */
        public function AddLog($accountID, $deviceID, $type, $data = null) {
            $IP = GetClientIP();
            $command = 'INSERT INTO TABLE (`AccountID`, `DeviceID`, `IP`, `Type`, `Data`) VALUES (?, ?, ?, ?, ?)';
            $args = [ $accountID, $deviceID, $IP, $type, $data ];
            $result = $this->QueryPrepare('Logs', $command, 'iisss', $args);
            return $result !== false;
        }
    }

?>
