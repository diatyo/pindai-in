<?php

use App\Models\BorrowerModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class BorrowerClassesTest extends CIUnitTestCase
{
    private BaseConnection $classDb;
    private BorrowerModel $borrowers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classDb = Database::connect('tests', false);
        if ($this->classDb->DBDriver !== 'SQLite3' || $this->classDb->database !== ':memory:') {
            throw new RuntimeException('Class filter tests require a private in-memory database.');
        }
        $table = $this->classDb->escapeIdentifiers($this->classDb->prefixTable('borrowers'));
        $this->classDb->query('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY, type TEXT, is_active INTEGER, class_name TEXT, name TEXT, identity_number TEXT)');
        foreach (['IX.9', 'VII.10', 'VIII.5', 'VII.2', 'VII.2', null, '   '] as $index => $class) {
            $this->classDb->table('borrowers')->insert(['id' => $index + 1, 'type' => 'student', 'is_active' => 1,
                'class_name' => $class, 'name' => 'Siswa ' . $index, 'identity_number' => '123' . $index]);
        }
        $this->classDb->table('borrowers')->insertBatch([
            ['id' => 8, 'type' => 'student', 'is_active' => 0, 'class_name' => 'VIII.99', 'name' => 'Arsip'],
            ['id' => 9, 'type' => 'teacher', 'is_active' => 1, 'class_name' => 'Kelas guru', 'name' => 'Guru'],
        ]);
        $this->borrowers = new BorrowerModel($this->classDb);
    }

    protected function tearDown(): void
    {
        $this->classDb->close();
        parent::tearDown();
    }

    public function testClassChoicesUseAllActiveStudentsAndNaturalGradeOrder(): void
    {
        self::assertSame(['VII.2', 'VII.10', 'VIII.5', 'IX.9'], $this->borrowers->activeStudentClasses());
        $this->classDb->table('borrowers')->insert(['id' => 10, 'type' => 'student', 'is_active' => 1, 'class_name' => 'IX.10', 'name' => 'Siswa baru']);
        self::assertSame(['VII.2', 'VII.10', 'VIII.5', 'IX.9', 'IX.10'], $this->borrowers->activeStudentClasses());
    }

    public function testEachChoiceMatchesTheActualStudentSearch(): void
    {
        $rows = $this->borrowers->students(null, 'VII.2');
        self::assertCount(2, $rows);
        self::assertSame(['VII.2', 'VII.2'], array_column($rows, 'class_name'));
        self::assertSame([], $this->borrowers->students(null, 'VIII.99'));
        self::assertCount(1, $this->borrowers->students('Siswa 2', 'VIII.5'));
    }
}
