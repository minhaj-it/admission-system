# Running the tests on MySQL

The error `could not find driver (Connection: sqlite ...)` means this PHP has no SQLite driver, and the
skeleton's `phpunit.xml` runs the tests on in-memory SQLite. Two ways to fix it - use ONE of them.

## A. Run the tests on MySQL (recommended if you use MySQL anyway)

1. Create a **separate** empty database, for example `admission_test` (phpMyAdmin > New).
   Never use your real database: every test run erases all tables of the database it runs on.
2. Copy `.env.testing.example` to `.env.testing` and put your MySQL user/password in it.
   For `APP_KEY` copy the value from your normal `.env`.
3. Open `phpunit.xml` and replace these two lines
   ```xml
   <env name="DB_CONNECTION" value="sqlite"/>
   <env name="DB_DATABASE" value=":memory:"/>
   ```
   with
   ```xml
   <env name="DB_CONNECTION" value="mysql"/>
   <env name="DB_DATABASE" value="admission_test"/>
   ```
   (if the lines are commented out with `<!-- -->`, remove the comment markers and edit them the same way).
4. `php artisan config:clear` then `php artisan test`.

The new `tests/TestCase.php` refuses to run on any non-SQLite database whose name does not end with `_test`.

## B. Enable SQLite in PHP instead

In `php.ini` (XAMPP: Config > PHP) remove the `;` before `extension=pdo_sqlite` and `extension=sqlite3`,
restart the terminal, check with `php -m | grep -i sqlite`, and run `php artisan test` unchanged.
