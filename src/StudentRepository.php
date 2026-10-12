<?php
declare(strict_types=1);

// ============================================================
//  src/StudentRepository.php — the data layer.
//
//  The rest of the app only ever talks to the StudentRepository
//  INTERFACE. Whether data comes from memory or MySQL is an
//  implementation detail, chosen once in bootstrap.php.
//
//  This is the "swap point": adding the database does NOT touch
//  index.php, Auth, or Response — only this file and .env.
// ============================================================

interface StudentRepository
{
    /** Return the student array, or null if the reg number is unknown. */
    public function findByRegNumber(string $regNumber): ?array;
}

final class WaecAggregateCalculator
{
    private const GRADE_POINTS = [
        'A1' => 5.0,
        'B2' => 4.5,
        'B3' => 4.0,
        'C4' => 3.5,
        'C5' => 3.0,
        'C6' => 2.5,
        'D7' => 2.0,
        'E8' => 1.0,
        'F9' => 0.0,
    ];

    public static function calculate(?array $results): ?float
    {
        if ($results === null || $results === []) {
            return null;
        }

        $total = 0.0;
        foreach ($results as $grade) {
            $grade = strtoupper(trim((string) $grade));
            if (!array_key_exists($grade, self::GRADE_POINTS)) {
                return null;
            }
            $total += self::GRADE_POINTS[$grade];
        }

        return ($total / (count($results) * 5)) * 100;
    }
}

// ---------------- Prototype: hardcoded data ----------------
final class InMemoryStudentRepository implements StudentRepository
{
    public function findByRegNumber(string $regNumber): ?array
    {
        $students = [
            '7638272HC' => [
                'full_name'    => 'Eniola Afolayan',
                'phone_number' => '09165163050',
                'email'        => 'afolayaneniola@gmail.com',
                'waec_result'  => [
                    'english'     => 'B3',
                    'mathematics' => 'C4',
                    'literature'  => 'C5',
                    'government'  => 'C5',
                    'crs'         => 'C5',
                ],
                'utme_result'  => [
                    'english'    => 61,
                    'maths'      => 87,
                    'literature' => 84,
                    'government' => 77,
                ],
                'aggregate'    => 66.0,
            ],
        ];

        $student = $students[$regNumber] ?? null;
        if ($student !== null) {
            $student['aggregate'] = WaecAggregateCalculator::calculate($student['waec_result']);
        }

        return $student;
    }
}

// ---------------- Production: MySQL via PDO ----------------
final class PdoStudentRepository implements StudentRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByRegNumber(string $regNumber): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT full_name, phone_number, email, waec_result, utme_result, aggregate
               FROM students
              WHERE reg_number = :reg'
        );
        $stmt->execute(['reg' => $regNumber]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        // These columns are nullable JSON text in MySQL.
        $row['waec_result'] = $row['waec_result'] === null
            ? null
            : json_decode($row['waec_result'], true);
        $row['utme_result'] = $row['utme_result'] === null
            ? null
            : json_decode($row['utme_result'], true);
        $row['aggregate'] = WaecAggregateCalculator::calculate($row['waec_result']);

        return $row;
    }
}
