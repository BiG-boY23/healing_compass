<?php
/**
 * Simple TOTP Helper (No external dependencies)
 * Based on Google Authenticator implementation
 */
class TOTPHelper {
    private static $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret($length = 16) {
        $secret = '';
        while (strlen($secret) < $length) {
            $secret .= self::$base32chars[rand(0, 31)];
        }
        return $secret;
    }

    public static function verifyToken($secret, $token, $discrepancy = 1) {
        $currentTime = floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedToken = self::calculateToken($secret, $currentTime + $i);
            if ($calculatedToken == $token) {
                return true;
            }
        }
        return false;
    }

    private static function calculateToken($secret, $time) {
        $binarySecret = self::base32Decode($secret);
        $timeHex = str_pad(dechex($time), 16, "0", STR_PAD_LEFT);
        $timeBin = hex2bin($timeHex);
        
        $hash = hash_hmac('sha1', $timeBin, $binarySecret, true);
        $offset = ord($hash[19]) & 0xf;
        
        $otp = (
            ((ord($hash[$offset+0]) & 0x7f) << 24) |
            ((ord($hash[$offset+1]) & 0xff) << 16) |
            ((ord($hash[$offset+2]) & 0xff) << 8) |
            (ord($hash[$offset+3]) & 0xff)
        ) % 1000000;
        
        return str_pad($otp, 6, "0", STR_PAD_LEFT);
    }

    private static function base32Decode($str) {
        $str = strtoupper($str);
        if (!preg_match('/^[A-Z2-7]+$/', $str)) return false;
        
        $binary = '';
        foreach (str_split($str) as $char) {
            $val = strpos(self::$base32chars, $char);
            $binary .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }
        
        $binChunks = str_split($binary, 8);
        $decoded = '';
        foreach ($binChunks as $chunk) {
            if (strlen($chunk) < 8) break;
            $decoded .= chr(bindec($chunk));
        }
        return $decoded;
    }

    public static function getQRUrl($username, $secret, $issuer = "HealingCompass") {
        $encodedUsername = urlencode($username);
        $encodedIssuer = urlencode($issuer);
        return "otpauth://totp/{$encodedIssuer}:{$encodedUsername}?secret={$secret}&issuer={$encodedIssuer}";
    }
}
