# Database Rules
- 100% Parameterized queries (`$stmt->prepare()` in PDO or ORM/SDK builders) to prevent SQL injection.
- Use environment variables (`.env`) to switch DB connection strings easily (SQLite locally vs MySQL/Postgres in production).
