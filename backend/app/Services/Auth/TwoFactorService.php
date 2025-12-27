<?php

namespace App\Services\Auth;

use App\Models\TwoFactorAuth;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function generateSecret(User $user): string
    {
        $secret = $this->google2fa->generateSecretKey();
        
        TwoFactorAuth::updateOrCreate(
            ['user_id' => $user->id],
            ['totp_secret' => $secret, 'enabled' => false]
        );

        return $secret;
    }

    public function getQRCodeUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );
    }

    public function verify(User $user, string $code): bool
    {
        $twoFactor = TwoFactorAuth::where('user_id', $user->id)->first();
        
        if (!$twoFactor || !$twoFactor->enabled) {
            return false;
        }

        return $this->google2fa->verifyKey($twoFactor->totp_secret, $code);
    }

    public function verifyWithRecovery(User $user, string $code): bool
    {
        $twoFactor = TwoFactorAuth::where('user_id', $user->id)->first();
        
        if (!$twoFactor || !$twoFactor->enabled) {
            return false;
        }

        // Try TOTP code first
        if ($this->google2fa->verifyKey($twoFactor->totp_secret, $code)) {
            return true;
        }

        // Try recovery codes
        $recoveryCodes = $twoFactor->recovery_codes_json ?? [];
        if (in_array($code, $recoveryCodes)) {
            // Remove used recovery code
            $recoveryCodes = array_values(array_diff($recoveryCodes, [$code]));
            $twoFactor->recovery_codes_json = $recoveryCodes;
            $twoFactor->save();
            return true;
        }

        return false;
    }

    public function enable(User $user, string $code): bool
    {
        $twoFactor = TwoFactorAuth::where('user_id', $user->id)->first();
        
        if (!$twoFactor || !$twoFactor->totp_secret) {
            return false;
        }

        // Verify code with secret (not enabled yet)
        if (!$this->google2fa->verifyKey($twoFactor->totp_secret, $code)) {
            return false;
        }

        $twoFactor->enabled = true;
        $twoFactor->recovery_codes_json = $this->generateRecoveryCodes();
        $twoFactor->save();

        return true;
    }

    public function disable(User $user, string $code): bool
    {
        if (!$this->verifyWithRecovery($user, $code)) {
            return false;
        }

        $twoFactor = TwoFactorAuth::where('user_id', $user->id)->first();
        $twoFactor->enabled = false;
        $twoFactor->save();

        return true;
    }

    public function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn() => bin2hex(random_bytes(4)))->toArray();
    }
}

