# KTP Import XLSX Format

This document describes the expected XLSX format for importing KTP (Calendar-Thematic Planning) topics.

## Columns

| Column Name         | Description                                     | Type      | Required | Example             |
|---------------------|-------------------------------------------------|-----------|----------|---------------------|
| `group`             | Name of the group the KTP is for                | String    | Yes      | `Group A`           |
| `subject`           | Name of the subject                             | String    | Yes      | `Mathematics`       |
| `term`              | Name of the academic term                       | String    | Yes      | `Fall 2024`         |
| `order_no`          | Order number of the topic within the KTP        | Integer   | Yes      | `1`                 |
| `title`             | Title of the KTP topic                          | String    | Yes      | `Introduction to Algebra` |
| `hours`             | Number of hours allocated for the topic         | Numeric   | Yes      | `2.5`               |
| `control_type`      | Type of control (e.g., `exam`, `test`, `homework`) | String    | No       | `test`              |
| `planned_date_from` | Planned start date for the topic                | Date      | No       | `2024-09-01`        |
| `planned_date_to`   | Planned end date for the topic                  | Date      | No       | `2024-09-05`        |

## Example Data

| group     | subject     | term      | order_no | title                   | hours | control_type | planned_date_from | planned_date_to |
|-----------|-------------|-----------|----------|-------------------------|-------|--------------|-------------------|-----------------|
| Group A   | Mathematics | Fall 2024 | 1        | Introduction to Algebra | 2.5   |              | 2024-09-01        | 2024-09-05      |
| Group A   | Mathematics | Fall 2024 | 2        | Linear Equations        | 3     | test         | 2024-09-08        | 2024-09-12      |
| Group B   | Physics     | Fall 2024 | 1        | Mechanics Fundamentals  | 4     | exam         | 2024-09-01        | 2024-09-10      |

## Notes

- If a `CurriculumPlan` doesn't exist for the specified `group`, `subject`, and `term` combination, it will be automatically created.
- Topics with the same `order_no` within the same plan will be updated (not duplicated).
- The `hours` field sets both `hours` and `hours_total` in the database.
- Date fields should be in YYYY-MM-DD format.
