<?php

    /**
     * @link https://stackoverflow.com/questions/6770370/aes-256-encryption-in-php
     */
    class Encryption {
        private $keyA;
        private $keyB;
        private $cipher_algo = 'AES-256-CTR';

        /**
         * @param string $key Key to use for encryption from config.php
         * @param string $secondKey Second key to use for encryption,
         *                          based on the user's password hash or config.php
         */
        public function __construct($key, $secondKey) {
            $this->keyA = $key;
            $this->keyB = $secondKey;
        }

        /**
         * @param string $secondKey
         */
        public function DefineSecondKey($secondKey) {
            $this->keyB = $secondKey;
        }

        public static function HashPassword($password) {
            if (strlen($password) === 0) {
                return '';
            }
            return hash('sha512', $password);
        }

        /**
         * Encrypt with AES-256-CTR + HMAC-SHA-512
         * @param string $plaintext Your message
         * @return string
         */
        public function Encrypt($plaintext)
        {
            $nonce = random_bytes(16);
            $ciphertext = openssl_encrypt(
                $plaintext,
                $this->cipher_algo,
                $this->keyA,
                OPENSSL_RAW_DATA,
                $nonce
            );
            $keyB = hash('ripemd128', $this->keyB);
            $mac = hash_hmac('sha512', $nonce.$ciphertext, $keyB, true);
            return base64_encode($mac.$nonce.$ciphertext);
        }

        /**
         * Verify HMAC-SHA-512 then decrypt AES-256-CTR
         * @param string $message Encrypted message
         * @return string|null
         */
        public function Decrypt($message) {
            $decoded = base64_decode($message);
            $mac = mb_substr($decoded, 0, 64, '8bit');
            $nonce = mb_substr($decoded, 64, 16, '8bit');
            $ciphertext = mb_substr($decoded, 80, null, '8bit');

            $keyB = hash('ripemd128', $this->keyB);
            $calc = hash_hmac('sha512', $nonce.$ciphertext, $keyB, true);
            if (!hash_equals($calc, $mac)) {
                return null;
            }
            return openssl_decrypt(
                $ciphertext,
                $this->cipher_algo,
                $this->keyA,
                OPENSSL_RAW_DATA,
                $nonce
            );
        }
    }

?>