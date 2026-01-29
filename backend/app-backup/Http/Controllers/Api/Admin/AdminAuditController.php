<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\DTO\Audit\AuditDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AdminAuditController extends Controller
{
    /**
     * Get audit logs with filtering
     * GET /api/admin/audit?entity=&action=&userId=&dateFrom=&dateTo=&search=
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = app('tenant_id');
        $maskSensitive = $this->shouldMaskSensitiveData();

        $query = AuditLog::query()
            ->with('user')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc');

        // Filter by entity
        if ($request->has('entity') && $request->filled('entity')) {
            $query->where('entity', $request->input('entity'));
        }

        // Filter by action
        if ($request->has('action') && $request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        // Filter by userId
        if ($request->has('userId') && $request->filled('userId')) {
            $query->where('user_id', $request->input('userId'));
        }

        // Filter by dateFrom
        if ($request->has('dateFrom') && $request->filled('dateFrom')) {
            $query->whereDate('created_at', '>=', $request->input('dateFrom'));
        }

        // Filter by dateTo
        if ($request->has('dateTo') && $request->filled('dateTo')) {
            $query->whereDate('created_at', '<=', $request->input('dateTo'));
        }

        // Search in action, entity, before_json, after_json
        if ($request->has('search') && $request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('action', 'ilike', "%{$search}%")
                    ->orWhere('entity', 'ilike', "%{$search}%")
                    ->orWhereJsonContains('before_json', $search)
                    ->orWhereJsonContains('after_json', $search);
            });
        }

        $perPage = $request->integer('per_page', 50);
        $logs = $query->paginate($perPage);

        // Transform to DTOs
        $data = $logs->getCollection()->map(function ($log) use ($maskSensitive) {
            return $this->toDTO($log, $maskSensitive);
        });

        return response()->json([
            'data' => $data,
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'per_page' => $logs->perPage(),
            'total' => $logs->total(),
        ]);
    }

    /**
     * Get single audit log
     * GET /api/admin/audit/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = app('tenant_id');
        $maskSensitive = $this->shouldMaskSensitiveData();

        $log = AuditLog::where('tenant_id', $tenantId)
            ->with('user')
            ->findOrFail($id);

        $dto = $this->toDTO($log, $maskSensitive);

        return response()->json([
            'id' => $dto->id,
            'tenant_id' => $dto->tenantId,
            'user_id' => $dto->userId,
            'action' => $dto->action,
            'entity' => $dto->entity,
            'entity_id' => $dto->entityId,
            'before_json' => $dto->beforeJson,
            'after_json' => $dto->afterJson,
            'ip' => $dto->ip,
            'created_at' => $dto->createdAt->toIso8601String(),
            'user_fio' => $dto->userFio,
            'user_email' => $dto->userEmail,
        ]);
    }

    /**
     * Export audit logs to Excel
     * GET /api/admin/audit/export?format=xlsx
     */
    public function export(Request $request)
    {
        $request->validate([
            'format' => ['sometimes', 'string', 'in:xlsx'],
        ]);

        $tenantId = app('tenant_id');
        $maskSensitive = $this->shouldMaskSensitiveData();

        // Apply same filters as index
        $query = AuditLog::query()
            ->with('user')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc');

        if ($request->has('entity') && $request->filled('entity')) {
            $query->where('entity', $request->input('entity'));
        }

        if ($request->has('action') && $request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->has('userId') && $request->filled('userId')) {
            $query->where('user_id', $request->input('userId'));
        }

        if ($request->has('dateFrom') && $request->filled('dateFrom')) {
            $query->whereDate('created_at', '>=', $request->input('dateFrom'));
        }

        if ($request->has('dateTo') && $request->filled('dateTo')) {
            $query->whereDate('created_at', '<=', $request->input('dateTo'));
        }

        if ($request->has('search') && $request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('action', 'ilike', "%{$search}%")
                    ->orWhere('entity', 'ilike', "%{$search}%")
                    ->orWhereJsonContains('before_json', $search)
                    ->orWhereJsonContains('after_json', $search);
            });
        }

        return Excel::download(new class($query->get(), $maskSensitive) implements FromCollection, WithHeadings, WithMapping {
            private $logs;
            private bool $maskSensitive;

            public function __construct($logs, bool $maskSensitive)
            {
                $this->logs = $logs;
                $this->maskSensitive = $maskSensitive;
            }

            public function collection()
            {
                return $this->logs;
            }

            public function headings(): array
            {
                return ['ID', 'Дата', 'Пользователь', 'Действие', 'Сущность', 'ID сущности', 'IP', 'До', 'После'];
            }

            public function map($log): array
            {
                $userFio = $log->user?->fio ?? '';
                $userEmail = $log->user?->email ?? '';

                if ($this->maskSensitive) {
                    $userEmail = $this->maskEmail($userEmail);
                }

                $beforeJson = $log->before_json ? json_encode($this->maskSensitiveData($log->before_json, $this->maskSensitive)) : '';
                $afterJson = $log->after_json ? json_encode($this->maskSensitiveData($log->after_json, $this->maskSensitive)) : '';

                return [
                    $log->id,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $userFio . ($userEmail ? " ({$userEmail})" : ''),
                    $log->action,
                    $log->entity,
                    $log->entity_id ?? '',
                    $log->ip ?? '',
                    $beforeJson,
                    $afterJson,
                ];
            }

            private function maskSensitiveData(array $data, bool $mask): array
            {
                if (!$mask) {
                    return $data;
                }

                $masked = [];
                foreach ($data as $key => $value) {
                    if (is_array($value)) {
                        $masked[$key] = $this->maskSensitiveData($value, $mask);
                    } elseif (is_string($value)) {
                        if (in_array(strtolower($key), ['email', 'e-mail', 'mail'])) {
                            $masked[$key] = $this->maskEmail($value);
                        } elseif (in_array(strtolower($key), ['phone', 'telephone', 'tel', 'mobile'])) {
                            $masked[$key] = $this->maskPhone($value);
                        } else {
                            $masked[$key] = $value;
                        }
                    } else {
                        $masked[$key] = $value;
                    }
                }

                return $masked;
            }

            private function maskEmail(string $email): string
            {
                if (empty($email)) {
                    return '';
                }

                $parts = explode('@', $email);
                if (count($parts) !== 2) {
                    return '***';
                }

                $local = $parts[0];
                $domain = $parts[1];

                if (strlen($local) <= 2) {
                    $maskedLocal = str_repeat('*', strlen($local));
                } else {
                    $maskedLocal = substr($local, 0, 1) . str_repeat('*', strlen($local) - 2) . substr($local, -1);
                }

                return $maskedLocal . '@' . $domain;
            }

            private function maskPhone(string $phone): string
            {
                if (empty($phone)) {
                    return '';
                }

                // Keep last 4 digits, mask the rest
                $digits = preg_replace('/\D/', '', $phone);
                if (strlen($digits) <= 4) {
                    return str_repeat('*', strlen($digits));
                }

                $last4 = substr($digits, -4);
                $masked = str_repeat('*', strlen($digits) - 4) . $last4;

                // Preserve non-digit characters (+, -, spaces, etc.)
                $maskedPhone = preg_replace_callback('/\d/', function () use (&$masked) {
                    return substr($masked, 0, 1) . substr($masked, 1);
                }, $phone);

                return $masked;
            }
        }, 'audit_logs_' . date('Y-m-d_His') . '.xlsx');
    }

    /**
     * Convert AuditLog model to DTO
     */
    private function toDTO(AuditLog $log, bool $maskSensitive): AuditDTO
    {
        $userFio = $log->user?->fio ?? null;
        $userEmail = $log->user?->email ?? null;

        $beforeJson = $log->before_json;
        $afterJson = $log->after_json;

        if ($maskSensitive) {
            if ($userEmail) {
                $userEmail = $this->maskEmail($userEmail);
            }

            if ($beforeJson) {
                $beforeJson = $this->maskSensitiveData($beforeJson);
            }

            if ($afterJson) {
                $afterJson = $this->maskSensitiveData($afterJson);
            }
        }

        return new AuditDTO(
            id: $log->id,
            tenantId: $log->tenant_id,
            userId: $log->user_id,
            action: $log->action,
            entity: $log->entity,
            entityId: $log->entity_id,
            beforeJson: $beforeJson,
            afterJson: $afterJson,
            ip: $log->ip,
            createdAt: $log->created_at,
            userFio: $userFio,
            userEmail: $userEmail,
        );
    }

    /**
     * Mask sensitive data in array
     */
    private function maskSensitiveData(array $data): array
    {
        $masked = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = $this->maskSensitiveData($value);
            } elseif (is_string($value)) {
                if (in_array(strtolower($key), ['email', 'e-mail', 'mail'])) {
                    $masked[$key] = $this->maskEmail($value);
                } elseif (in_array(strtolower($key), ['phone', 'telephone', 'tel', 'mobile'])) {
                    $masked[$key] = $this->maskPhone($value);
                } else {
                    $masked[$key] = $value;
                }
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    /**
     * Mask email address
     */
    private function maskEmail(string $email): string
    {
        if (empty($email)) {
            return '';
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        if (strlen($local) <= 2) {
            $maskedLocal = str_repeat('*', strlen($local));
        } else {
            $maskedLocal = substr($local, 0, 1) . str_repeat('*', strlen($local) - 2) . substr($local, -1);
        }

        return $maskedLocal . '@' . $domain;
    }

    /**
     * Mask phone number
     */
    private function maskPhone(string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        // Keep last 4 digits, mask the rest
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) <= 4) {
            return str_repeat('*', strlen($digits));
        }

        $last4 = substr($digits, -4);
        return str_repeat('*', strlen($digits) - 4) . $last4;
    }

    /**
     * Check if sensitive data should be masked
     */
    private function shouldMaskSensitiveData(): bool
    {
        // Check setting for masking sensitive data
        // Default: mask if setting exists and is enabled
        $setting = Setting::where('key', 'audit.mask_sensitive_data')->first();
        
        if ($setting && isset($setting->value_json['enabled'])) {
            return (bool) $setting->value_json['enabled'];
        }

        // Default: mask sensitive data
        return true;
    }
}

