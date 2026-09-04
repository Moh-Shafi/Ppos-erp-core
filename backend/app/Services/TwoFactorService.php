<?php

namespace App\Services;

use App\Models\TwoFactorAuth;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class TwoFactorService
{
    public function generateSecret(): string
    {
        $secret = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $secret;
    }

    public function enable(User $user): array
    {
        $secret = $this->generateSecret();

        $tfa = TwoFactorAuth::updateOrCreate(
            ['user_id' => $user->id],
            [
                'secret' => Crypt::encrypt($secret),
                'backup_codes' => null,
                'enabled_at' => null,
            ],
        );

        $qrCode = $this->generateQrCode($user, $secret);
        $backupCodes = $this->generateBackupCodes();

        $tfa->backup_codes = array_map(fn ($code) => bcrypt($code), $backupCodes);
        $tfa->save();

        return [
            'qr_code' => $qrCode,
            'secret' => $secret,
            'backup_codes' => $backupCodes,
        ];
    }

    public function verify(User $user, string $code): bool
    {
        $tfa = $user->twoFactorAuth;

        if (!$tfa) {
            return false;
        }

        $secret = Crypt::decrypt($tfa->secret);
        $valid = $this->verifyTotp($secret, $code);

        if ($valid) {
            if (!$user->two_factor_enabled) {
                $user->two_factor_enabled = true;
                $user->two_factor_verified_at = now();
                $user->save();

                $tfa->enabled_at = now();
                $tfa->save();
            }

            $tfa->last_used_at = now();
            $tfa->save();

            return true;
        }

        return false;
    }

    public function disable(User $user, string $code): bool
    {
        $tfa = $user->twoFactorAuth;

        if (!$tfa) {
            return false;
        }

        $secret = Crypt::decrypt($tfa->secret);

        if (!$this->verifyTotp($secret, $code)) {
            return false;
        }

        $user->two_factor_enabled = false;
        $user->two_factor_verified_at = null;
        $user->save();

        $tfa->delete();

        return true;
    }

    public function verifyBackupCode(User $user, string $code): bool
    {
        $tfa = $user->twoFactorAuth;

        if (!$tfa || !$tfa->backup_codes) {
            return false;
        }

        $backupCodes = $tfa->backup_codes;

        foreach ($backupCodes as $i => $hashed) {
            if (password_verify(strtoupper($code), $hashed)) {
                unset($backupCodes[$i]);
                $tfa->backup_codes = array_values($backupCodes);
                $tfa->save();
                return true;
            }
        }

        return false;
    }

    public function regenerateBackupCodes(User $user, string $code): ?array
    {
        $tfa = $user->twoFactorAuth;

        if (!$tfa) {
            return null;
        }

        $secret = Crypt::decrypt($tfa->secret);

        if (!$this->verifyTotp($secret, $code)) {
            return null;
        }

        $backupCodes = $this->generateBackupCodes();
        $tfa->backup_codes = array_map(fn ($c) => bcrypt($c), $backupCodes);
        $tfa->save();

        return $backupCodes;
    }

    public function resetForUser(User $user): void
    {
        $user->two_factor_enabled = false;
        $user->two_factor_verified_at = null;
        $user->save();

        $user->twoFactorAuth()->delete();
    }

    public function getBackupCodesRemaining(User $user): int
    {
        $tfa = $user->twoFactorAuth;

        if (!$tfa || !$tfa->backup_codes) {
            return 0;
        }

        return count($tfa->backup_codes);
    }

    public function verifyTotp(string $secret, string $code): bool
    {
        $window = config('security.two_factor.window', 1);
        $timeStep = 30;
        $currentTime = floor(time() / $timeStep);

        for ($i = -$window; $i <= $window; $i++) {
            $timeCounter = $currentTime + $i;
            $expectedCode = $this->calculateTotp($secret, $timeCounter);

            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    private function calculateTotp(string $secret, int $timeCounter): string
    {
        $binarySecret = $this->base32Decode($secret);
        $time = pack('N', 0) . pack('N', $timeCounter);
        $hash = hash_hmac('sha1', $time, $binarySecret, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            (ord($hash[$offset + 1]) & 0xFF) << 16 |
            (ord($hash[$offset + 2]) & 0xFF) << 8 |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0; $i < strlen($secret); $i++) {
            $value = strpos($chars, strtoupper($secret[$i]));
            if ($value === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }

    private function generateQrCode(User $user, string $secret): string
    {
        $issuer = config('security.two_factor.issuer', 'POS-SaaS');
        $label = rawurlencode($issuer . ':' . $user->email);
        $uri = "otpauth://totp/{$label}?secret={$secret}&issuer=" . rawurlencode($issuer);

        return $uri;
    }

    private function generateBackupCodes(): array
    {
        $count = config('security.two_factor.backup_codes_count', 10);
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(8));
        }

        return $codes;
    }
}
