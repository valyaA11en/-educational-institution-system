# Pack #9 - TODO

## 1. Автосоздание уроков ✓
- [x] LessonAutoCreationService
- [x] Команда AutoCreateLessons
- [x] Команда ScheduleAutoCreateLessons
- [x] Настройка в routes/console.php
- [ ] Интеграция с КТП (topic из плана)

## 2. Excel-like журнал
- [x] JournalBulkController::bulkUpdateGrades
- [ ] Frontend компонент для bulk редактирования
- [ ] Реализация bulkUpdateAttendance (нужна модель Attendance)

## 3. ГОСТ-документы
- [ ] Шаблоны DOCX для приказов/распоряжений
- [ ] Генерация PDF из DOCX
- [ ] Валидация формата согласно ГОСТ

## 4. WebSocket ACK + replay
- [ ] Хранение непрочитанных уведомлений
- [ ] ACK механизм
- [ ] Replay для восстановления после разрыва связи

## 5. Security test suite
- [x] Базовый тест ObjectLevelAccessTest
- [ ] Полный набор тестов на object-level доступ

## 6. Audit UI + отчёты
- [x] AuditController::index
- [x] AuditController::export (заглушка)
- [ ] Frontend страница /audit
- [ ] Экспорт в Excel/PDF

## 7. Импорт/миграции
- [x] DataImportService::importUsers (базовая версия)
- [x] DataImportController
- [ ] Полный импорт users
- [ ] Импорт groups
- [ ] Импорт schedule
- [ ] Импорт grades

## 8. File hygiene
- [x] FileHygieneService::cleanupOrphanFiles
- [x] Команда CleanupOrphanFiles
- [ ] Интеграция с ClamAV
- [ ] Проверка квот пользователей
- [ ] Полная проверка всех связей файлов


