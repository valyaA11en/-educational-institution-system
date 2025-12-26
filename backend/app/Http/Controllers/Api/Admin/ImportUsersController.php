<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportUsersXlsxRequest;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetException;

class ImportUsersController extends Controller
{
    public function importUsersXlsx(ImportUsersXlsxRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Skip header row (first row)
            $headerRow = array_shift($rows);
            $headerMap = $this->mapHeaders($headerRow);

            DB::beginTransaction();

            foreach ($rows as $rowIndex => $row) {
                $stats['total']++;

                // Skip empty rows
                if (empty(array_filter($row))) {
                    $stats['skipped']++;
                    continue;
                }

                try {
                    $userData = $this->parseRow($row, $headerMap, $rowIndex + 2); // +2 because we removed header and 0-indexed
                    $result = $this->createOrUpdateUser($userData);
                    $stats[$result]++;
                } catch (\Exception $e) {
                    $stats['errors'][] = [
                        'row' => $rowIndex + 2,
                        'message' => $e->getMessage(),
                    ];
                    $stats['skipped']++;
                    Log::error('User import error', [
                        'row' => $rowIndex + 2,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Импорт завершен',
                'stats' => $stats,
            ]);
        } catch (SpreadsheetException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка чтения файла: ' . $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import users error', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Ошибка импорта: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function mapHeaders(array $headerRow): array
    {
        $map = [];
        $expectedHeaders = [
            'fio' => ['фио', 'fio', 'name', 'имя'],
            'email' => ['email', 'почта', 'e-mail'],
            'phone' => ['phone', 'телефон', 'tel'],
            'password' => ['password', 'пароль', 'pwd'],
            'status' => ['status', 'статус'],
            'roles' => ['roles', 'роли', 'role'],
            'groups' => ['groups', 'группы', 'group'],
        ];

        foreach ($headerRow as $index => $header) {
            $headerLower = mb_strtolower(trim($header ?? ''));
            foreach ($expectedHeaders as $key => $variants) {
                if (in_array($headerLower, $variants, true)) {
                    $map[$key] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    private function parseRow(array $row, array $headerMap, int $rowNumber): array
    {
        $getValue = function (string $key, $default = null) use ($row, $headerMap) {
            return isset($headerMap[$key]) && isset($row[$headerMap[$key]])
                ? trim($row[$headerMap[$key]] ?? '')
                : $default;
        };

        $fio = $getValue('fio');
        if (empty($fio)) {
            throw new \Exception("Строка {$rowNumber}: ФИО обязательно");
        }

        $email = $getValue('email');
        $phone = $getValue('phone');

        if (empty($email) && empty($phone)) {
            throw new \Exception("Строка {$rowNumber}: Email или телефон обязательны");
        }

        $password = $getValue('password', 'Password123!'); // Default password
        $status = $getValue('status', 'active');

        $roles = $this->parseCommaSeparated($getValue('roles', ''));
        $groups = $this->parseCommaSeparated($getValue('groups', ''));

        return [
            'fio' => $fio,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'password' => $password,
            'status' => $status,
            'roles' => $roles,
            'groups' => $groups,
        ];
    }

    private function parseCommaSeparated(string $value): array
    {
        if (empty($value)) {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }

    private function createOrUpdateUser(array $userData): string
    {
        // Find existing user by email or phone
        $user = null;
        if ($userData['email']) {
            $user = User::where('email', $userData['email'])->first();
        }
        if (!$user && $userData['phone']) {
            $user = User::where('phone', $userData['phone'])->first();
        }

        $isNew = !$user;

        if ($isNew) {
            $user = new User();
        }

        $user->fio = $userData['fio'];
        $user->email = $userData['email'];
        $user->phone = $userData['phone'];
        $user->status = $userData['status'];

        // Only update password if provided or new user
        if ($isNew || !empty($userData['password'])) {
            $user->password_hash = Hash::make($userData['password']);
        }

        $user->save();

        // Assign roles
        if (!empty($userData['roles'])) {
            $roleIds = [];
            foreach ($userData['roles'] as $roleName) {
                $role = Role::where('name', $roleName)->first();
                if ($role) {
                    $roleIds[] = $role->id;
                }
            }
            if (!empty($roleIds)) {
                $user->roles()->sync($roleIds);
            }
        }

        // Assign groups
        if (!empty($userData['groups'])) {
            $groupIds = [];
            foreach ($userData['groups'] as $groupName) {
                $group = Group::where('name', $groupName)
                    ->orWhere('code', $groupName)
                    ->first();
                if ($group) {
                    $groupIds[$group->id] = ['role_in_group' => 'student']; // Default role
                }
            }
            if (!empty($groupIds)) {
                $user->groups()->sync($groupIds);
            }
        }

        return $isNew ? 'created' : 'updated';
    }
}


