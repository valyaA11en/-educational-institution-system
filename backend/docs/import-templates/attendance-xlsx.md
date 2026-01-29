# Импорт посещаемости (attendance-xlsx)

## Endpoint
POST /api/admin/import/attendance-xlsx

## Параметры
- `file` (required): Файл XLSX/XLS (макс. 10MB)
- `dryRun` (optional, boolean): Если `true`, проверяет данные без записи в БД

## Формат файла

### Колонки (в порядке):
1. **date** (обязательно) - Дата урока в формате YYYY-MM-DD
2. **group** (обязательно) - Название группы
3. **student_email** (обязательно) - Email студента
4. **status** (обязательно) - Статус посещаемости: `present`, `absent`, `late`, `excused`
5. **reason** (опционально) - Причина отсутствия (макс. 500 символов)

### Пример:
```
date        | group      | student_email      | status   | reason
2024-01-15  | Группа-1   | student@example.com| present  |
2024-01-15  | Группа-1   | student2@example.com| absent  | Болезнь
2024-01-15  | Группа-1   | student3@example.com| late    | Опоздание
2024-01-15  | Группа-1   | student4@example.com| excused | Уважительная причина
```

### Валидация:
- `date`: обязательное поле, формат даты (YYYY-MM-DD)
- `group`: обязательное поле, должна существовать в системе
- `student_email`: обязательное поле, валидный email, студент должен существовать в системе
- `status`: обязательное поле, одно из: `present`, `absent`, `late`, `excused`
- `reason`: опционально, максимум 500 символов

### Логика:
- Система ищет урок по дате и группе (первый урок дня для группы)
- Если урок не найден, создаётся новая позиция расписания и урок
- Если запись посещаемости уже существует, она обновляется

### Ответ при успехе:
```json
{
  "message": "Импорт успешно завершен",
  "dry_run": false,
  "success_count": 10
}
```

### Ответ при ошибках:
```json
{
  "message": "Импорт завершен с ошибками",
  "dry_run": false,
  "success_count": 8,
  "errors": [
    {
      "row": 3,
      "message": "Студент не найден: wrong@example.com"
    },
    {
      "row": 5,
      "message": "Статус должен быть одним из: present, absent, late, excused"
    }
  ]
}
```


