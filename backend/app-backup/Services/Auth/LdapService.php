<?php

namespace App\Services\Auth;

use App\Models\LdapConfig;
use Illuminate\Support\Facades\Log;

class LdapService
{
    public function authenticate(string $username, string $password, ?int $tenantId = null): ?array
    {
        $config = LdapConfig::where('enabled', true)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->first();

        if (!$config) {
            return null;
        }

        try {
            $connection = @ldap_connect($config->host, $config->port);
            
            if (!$connection) {
                Log::error('LDAP connection failed', ['host' => $config->host]);
                return null;
            }

            ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

            $bind = @ldap_bind($connection, $config->bind_dn, $config->bind_password);
            
            if (!$bind) {
                Log::error('LDAP bind failed');
                return null;
            }

            $filter = $config->user_filter ?: "(uid={$username})";
            $search = @ldap_search($connection, $config->base_dn, $filter);
            
            if (!$search) {
                return null;
            }

            $entries = ldap_get_entries($connection, $search);
            
            if ($entries['count'] !== 1) {
                return null;
            }

            $userDn = $entries[0]['dn'];
            $userBind = @ldap_bind($connection, $userDn, $password);
            
            if (!$userBind) {
                return null;
            }

            $mapping = $config->attribute_mapping ?? [];
            $userData = [
                'email' => $entries[0][$mapping['email'] ?? 'mail'][0] ?? null,
                'fio' => $entries[0][$mapping['fio'] ?? 'cn'][0] ?? null,
                'username' => $username,
            ];

            ldap_close($connection);

            return $userData;
        } catch (\Exception $e) {
            Log::error('LDAP authentication error', ['error' => $e->getMessage()]);
            return null;
        }
    }
}


