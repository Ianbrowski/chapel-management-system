<?php
/**
 * RateLimiter Helper
 * Thread-safe, file-backed rate limiter with sliding window decay expiry.
 * Protects public and sensitive endpoints from brute-force and request flooding.
 * Stores attempts securely in storage/rate_limits/.
 */
class RateLimiter
{
    private static string $storageDir = '';

    private static function getStorageDir(): string
    {
        if (self::$storageDir === '') {
            $base = defined('BASE_PATH') ? BASE_PATH : realpath(__DIR__ . '/../..');
            self::$storageDir = $base . '/storage/rate_limits';
            if (!is_dir(self::$storageDir)) {
                @mkdir(self::$storageDir, 0755, true);
            }
        }
        return self::$storageDir;
    }

    /**
     * Resolves client IP address safely.
     */
    public static function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
    }

    private static function getFilePath(string $key): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_\-\.:]/', '_', $key);
        $hash = sha1($key);
        return self::getStorageDir() . '/' . substr($clean, 0, 30) . '_' . $hash . '.json';
    }

    /**
     * Checks if a given key has exceeded maxAttempts within decaySeconds.
     */
    public static function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds = 60): bool
    {
        $attempts = self::getAttempts($key, $decaySeconds);
        return count($attempts) >= $maxAttempts;
    }

    /**
     * Records a new attempt for the key.
     */
    public static function hit(string $key, int $decaySeconds = 60): int
    {
        $file = self::getFilePath($key);
        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            $attempts = self::getAttempts($key, $decaySeconds);
            $attempts[] = time();
            self::saveAttempts($key, $attempts);
            return count($attempts);
        }
        flock($fh, LOCK_EX);
        $raw = stream_get_contents($fh);
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $cutoff = time() - $decaySeconds;
        $attempts = array_values(array_filter($decoded, fn($t) => is_int($t) && $t >= $cutoff));
        $attempts[] = time();
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($attempts));
        fflush($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        return count($attempts);
    }

    /**
     * Returns the number of seconds until the key is available again.
     */
    public static function availableIn(string $key, int $decaySeconds = 60): int
    {
        $attempts = self::getAttempts($key, $decaySeconds);
        if (empty($attempts)) {
            return 0;
        }
        $oldest = min($attempts);
        $availableAt = $oldest + $decaySeconds;
        return max(0, $availableAt - time());
    }

    /**
     * Returns remaining attempts allowed.
     */
    public static function remaining(string $key, int $maxAttempts, int $decaySeconds = 60): int
    {
        $attempts = self::getAttempts($key, $decaySeconds);
        return max(0, $maxAttempts - count($attempts));
    }

    /**
     * Clears recorded attempts for a key.
     */
    public static function clear(string $key): void
    {
        $file = self::getFilePath($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private static function getAttempts(string $key, int $decaySeconds): array
    {
        $file = self::getFilePath($key);
        if (!is_file($file)) {
            return [];
        }
        $fh = @fopen($file, 'r');
        if ($fh === false) {
            return [];
        }
        flock($fh, LOCK_SH);
        $data = stream_get_contents($fh);
        flock($fh, LOCK_UN);
        fclose($fh);
        if (!is_string($data) || $data === '') {
            return [];
        }
        $attempts = json_decode($data, true);
        if (!is_array($attempts)) {
            return [];
        }
        $cutoff = time() - $decaySeconds;
        return array_values(array_filter($attempts, fn($t) => is_int($t) && $t >= $cutoff));
    }

    private static function saveAttempts(string $key, array $attempts): void
    {
        $file = self::getFilePath($key);
        if (empty($attempts)) {
            if (is_file($file)) {
                @unlink($file);
            }
            return;
        }
        @file_put_contents($file, json_encode(array_values($attempts)), LOCK_EX);
    }
}
