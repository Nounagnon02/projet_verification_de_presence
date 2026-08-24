<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class TmpDiagPdfTest extends TestCase
{
    public function test_diag_pdf(): void
    {
        $sfx = Str::random(6);
        $jeton = User::factory()->create(['email' => 'diag-' . $sfx . '@test.local'])
            ->createToken('test')->plainTextToken;

        // Cas A : nom + mime corrects (ce que le test voulait faire)
        $f = UploadedFile::fake()->create('structure.pdf', 1, 'application/pdf');
        file_put_contents($f->getPathname(), '%PDF-1.4 contenu binaire');
        $r = $this->withToken($jeton)->postJson('/api/admin/import/csv/courses', ['file' => $f]);
        dump('A nom+mime', $r->getStatusCode(), $r->json());

        // Cas B : nom seul, mime null
        $f2 = UploadedFile::fake()->create('structure.pdf', 1);
        file_put_contents($f2->getPathname(), '%PDF-1.4 contenu binaire');
        $r2 = $this->withToken($jeton)->postJson('/api/admin/import/csv/courses', ['file' => $f2]);
        dump('B nom seul', $r2->getStatusCode(), $r2->json());

        // Cas C : reproduction exacte de l'appel actuel (jeton = 'structure.pdf')
        $f3 = UploadedFile::fake()->create('application/pdf', 1, null);
        file_put_contents($f3->getPathname(), '%PDF-1.4 contenu binaire');
        $r3 = $this->withToken('structure.pdf')->postJson('/api/admin/import/csv/courses', ['file' => $f3]);
        dump('C appel actuel', $r3->getStatusCode(), $r3->json());

        $this->assertTrue(true);
    }
}
