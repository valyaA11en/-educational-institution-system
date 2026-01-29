<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    private const TABLE = 'user_2fa';
    private const TEMP_TABLE = '2fa_temp_tokens';

    private Google2FA $google2fa;

    public function __construct(?Google2FA $google2fa = null)
    {
        $this->google2fa = $google2fa ?? new Google2FA();
    }

    public function generateSecret(User $user): string
    {
        $secret = $this->google2fa->generateSecretKey();
        $exists = DB::table(self::TABLE)->where('user_id', $user->id)->exists();
        if ($exists) {
            DB::table(self::TABLE)->where('user_id', $user->id)->update([
                'totp_secret' => $secret,
                'enabled' => 0,
                'recovery_codes_json' => null,
                'updated_at' => now(),
            ]);
        } else {
            DB::table(self::TABLE)->insert([
                'user_id' => $user->id,
                'totp_secret' => $secret,
                'enabled' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return $secret;
    }

    public function getQRCodeUrl(User $user, string $secret): string
    {
        $issuer = config('app.name');
        $label = $user->email ?: 'user_' . $user->id;
        return $this->google2fa->getQRCodeUrl($issuer, $label, $secret);
    }

    public function verify(User $user, string $code): bool
    {
        $row = DB::table(self::TABLE)->where('user_id', $user->id)->first();
        if (!$row || !$row->enabled || empty($row->totp_secret)) {
            return false;
        }
        return $this->google2fa->verifyKey($row->totp_secret, $code);
    }

    public function verifyWithRecovery(User $user, string $code): bool
    {
        $row = DB::table(self::TABLE)->where('user_id', $user->id)->first();
        if (!$row || !$row->enabled || empty($row->totp_secret)) {
            return false;
        }
        if ($this->google2fa->verifyKey($row->totp_secret, $code)) {
            return true;
        }
        $recovery = json_decode($row->recovery_codes_json ?? '[]', true);
        if (!is_array($recovery) || !in_array($code, $recovery, true)) {
            return false;
        }
        $recovery = array_values(array_diff($recovery, [$code]));
        DB::table(self::TABLE)->where('user_id', $user->id)->update([
            'recovery_codes_json' => json_encode($recovery),
            'updated_at' => now(),
        ]);
        return true;
    }

    public function enable(User $user, string $code): bool
    {
        $row = DB::table(self::TABLE)->where('user_id', $user->id)->first();
        if (!$row || empty($row->totp_secret)) {
            return false;
        }
        if (!$this->google2fa->verifyKey($row->totp_secret, $code)) {
            return false;
        }
        $codes = $this->generateRecoveryCodes();
        DB::table(self::TABLE)->where('user_id', $user->id)->update([
            'enabled' => 1,
            'recovery_codes_json' => json_encode($codes),
            'updated_at' => now(),
        ]);
        return true;
    }

    public function disable(User $user, string $code): bool
    {
        if (!$this->verifyWithRecovery($user, $code)) {
            return false;
        }
        DB::table(self::TABLE)->where('user_id', $user->id)->update([
            'enabled' => 0,
            'recovery_codes_json' => null,
            'updated_at' => now(),
        ]);
        return true;
    }

    /** @return string[] */
    public function generateRecoveryCodes(): array
    {
        $out = [];
        for ($i = 0; $i < 8; $i++) {
            $out[] = bin2hex(random_bytes(4));
        }
        return $out;
    }

    public function getRecoveryCodes(User $user): array
    {
        $row = DB::table(self::TABLE)->where('user_id', $user->id)->first();
        if (!$row || empty($row->recovery_codes_json)) {
            return [];
        }
        $v = json_decode($row->recovery_codes_json, true);
        return is_array($v) ? $v : [];
    }

    public function createTempToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $expires = now()->addMinutes(5);
        DB::table(self::TEMP_TABLE)->insert([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => $expires,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $token;
    }

    public function getUserFromTempToken(string $token): ?User
    {
        $row = DB::table(self::TEMP_TABLE)
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();
        return $row ? User::find($row->user_id) : null;
    }

    public function deleteTempToken(string $token): void
    {
        DB::table(self::TEMP_TABLE)->where('token', $token)->delete();
    }

    public function is2FAEnabled(User $user): bool
    {
        $row = DB::table(self::TABLE)->where('user_id', $user->id)->first();
        return $row && $row->enabled && !empty($row->totp_secret);
    }
}
