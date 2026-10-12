# Student Results API

A small PHP and MySQL service for looking up student examination results. It provides an authenticated JSON API for client applications and a browser-based, print-friendly result sheet.

## Requirements

- PHP 8.0 or newer
- MySQL or MariaDB
- PHP extensions: `pdo_mysql` for MySQL access and `curl` to run the sample client
- Apache with `mod_rewrite` for Apache deployments, or PHP's built-in server for local development

## Setup

1. Copy `.env.example` to `.env`.
2. Set `API_BEARER_TOKEN` to a long, randomly generated secret. Do not commit `.env` or share this token.
3. Set the database settings (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) for your MySQL server. The defaults target a local XAMPP database named `results_db`.
4. Import `schema.example.sql` into MySQL once to create the empty database and table. The local `schema.sql` contains real student data and is intentionally excluded from Git. The example schema is for initial setup, not an idempotent migration; do not rerun it over an existing populated database.
5. Ensure the PHP runtime serving the app has `pdo_mysql` enabled.

The repository driver is selected by `REPOSITORY_DRIVER`:

- `pdo` reads from MySQL and is the configured production mode.
- `memory` uses the prototype's in-memory example data. That fixture currently contains only the example student defined in `src/StudentRepository.php`.

## Run Locally

From the project root, start PHP's built-in server with the public front controller:

```powershell
php -S localhost:8001 public/index.php
```

On XAMPP, use the PHP executable whose configuration has `pdo_mysql` enabled if `php` resolves to a different PHP installation:

```powershell
C:\xampp\php\php.exe -S localhost:8001 public/index.php
```

The API base URL is `http://localhost:8001`. The browser result lookup is at `http://localhost:8001/`.

For Apache, configure the document root to the project's `public/` directory, enable `mod_rewrite` and `.htaccess` overrides, and use the included `public/.htaccess` front-controller rules. Keep `.env`, `schema.sql`, and `src/` outside the document root.

## Health Check

```http
GET /health
```

Successful response:

```json
{"status":200,"message":"OK"}
```

## Student Lookup API

```http
POST /api/v1/students/lookup
Content-Type: application/json
Authorization: Bearer <API_BEARER_TOKEN>
```

Request body:

```json
{
  "reg_number": "STU12345"
}
```

The registration number must be a non-empty string and may contain at most 20 characters. The lookup is exact after trimming surrounding whitespace.

Example using cURL:

```sh
curl --request POST \
  --url http://localhost:8001/api/v1/students/lookup \
  --header "Authorization: Bearer $API_BEARER_TOKEN" \
  --header "Content-Type: application/json" \
  --data '{"reg_number":"STU12345"}'
```

Successful response:

```json
{
  "status": 200,
  "message": "Successfully retrieved",
  "data": {
    "full_name": "Example Student",
    "phone_number": null,
    "email": null,
    "waec_result": {
      "english": "B2",
      "mathematics": "C4",
      "literature": "C6",
      "government": "B2",
      "crs": "B2"
    },
    "utme_result": {
      "english": 80,
      "maths": 57,
      "literature": 64,
      "government": 90
    },
    "aggregate": 78
  }
}
```

### API Status Codes

| HTTP status | Meaning |
| --- | --- |
| `200` | Student found; response includes the student record. |
| `400` | Missing or invalid `reg_number`. |
| `401` | Missing or incorrect bearer token. |
| `404` | Route or registration number not found. |
| `500` | API bearer token is not configured. |

Database connectivity/configuration failures are server errors and should be diagnosed in the PHP/server logs; do not expose database details to clients.

## WAEC Aggregate

The `aggregate` returned by lookup is calculated from the recorded WAEC grades. Each grade is converted to points, the points are summed, and the total is normalized to a percentage out of 100 based on the number of subjects recorded:

```text
WAEC percentage = sum(grade points) / (number of recorded subjects * 5) * 100
```

| Grade | Points |
| --- | ---: |
| A1 | 5.0 |
| B2 | 4.5 |
| B3 | 4.0 |
| C4 | 3.5 |
| C5 | 3.0 |
| C6 | 2.5 |
| D7 | 2.0 |
| E8 | 1.0 |
| F9 | 0.0 |

For example, five grades worth 19.5 points produce `19.5 / 25 * 100 = 78%`. The aggregate stored in the database is retained for compatibility/administrative purposes; the repository recalculates the returned value from `waec_result`. If WAEC results are missing or contain an unsupported grade, the calculated aggregate is unavailable (`null`). UTME scores do not currently contribute to this aggregate.

## Browser Result Sheet

Open `/` to use the HTML lookup page. Submit the registration number in the form; browser lookup uses POST so the registration number is not placed in the URL. The page displays the student name, registration number, WAEC results, UTME results, and calculated WAEC percentage. It omits phone and email, escapes displayed values, and includes print styles.

Browser access uses a separate optional `RESULTS_VIEW_TOKEN`; do not reuse `API_BEARER_TOKEN`:

- With `RESULTS_VIEW_TOKEN` configured, browser lookups require that viewer key.
- Without it, the page allows keyless lookup only when served by PHP's built-in server from a loopback address (`127.0.0.1` or `::1`).
- On Apache or a non-local host, an unset viewer key disables browser lookup.

Use HTTPS for any non-local deployment. The viewer key is a shared credential, not individual user authentication; protect it and restrict who receives it.

## Sample Command-Line Client

`client/serverA.php` demonstrates a client making one authenticated API request. Configure the environment variables in the shell before running it:

```powershell
$env:API_BEARER_TOKEN = "your-api-token"
$env:RESULT_API_URL = "http://localhost:8001/api/v1/students/lookup"
php client/serverA.php 7638272QP
```

The registration number is optional; without it the sample defaults to `7638272HC`. This script is an example consumer and is not part of the API server's request path.

## Data Model

The `students` table is created by `schema.example.sql` and contains:

| Column | Description |
| --- | --- |
| `id` | Auto-increment primary key. |
| `reg_number` | Unique registration number (up to 20 characters). |
| `full_name` | Student's name. |
| `phone_number`, `email` | Optional contact fields. |
| `waec_result` | Optional JSON object mapping subjects to grades. |
| `utme_result` | Optional JSON object mapping subjects to scores. |
| `aggregate` | Optional stored percentage; the API recalculates it from WAEC grades. |

To add or update a student, write the row to MySQL with a unique `reg_number`. The local `schema.sql` file is ignored because it contains real student records. Editing a schema file only changes future database initialization; it does not update an already-created database.

## Security Notes

- Keep `.env` private and out of version control.
- Use separate, long random values for the API bearer token and browser viewer token.
- Use HTTPS outside local development.
- The API response includes phone and email when present; grant API tokens only to trusted clients.
- Restrict access to database credentials and backups. Do not put secrets in Postman collections, source files, URLs, or screenshots.# Result-Getter-API
